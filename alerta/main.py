"""Aviso diário dos boletos no Discord, separado por nível de alerta.

Uso:
    python main.py              envio normal (cron das 08:00)
    python main.py --lembrete   só envia se houver boleto Crítico (cron das 14:00)
    python main.py --dry-run    mostra o payload sem enviar
    python main.py --limite 2   usa só os N primeiros boletos (para testes)
"""

import argparse
import json
import os
from datetime import datetime, timedelta
from zoneinfo import ZoneInfo

import mysql.connector
import requests
from dotenv import load_dotenv


load_dotenv(os.path.join(os.path.dirname(os.path.abspath(__file__)), ".env"))

FUSO = ZoneInfo("America/Sao_Paulo")
LINK_BOLETOS = "https://dash.rillis.com.br/boletos/"

MYSQL_CONFIG = {
    "host": os.getenv("MYSQL_HOST", "127.0.0.1"),
    "port": int(os.getenv("MYSQL_PORT", "3306")),
    "user": os.getenv("MYSQL_USER"),
    "password": os.getenv("MYSQL_PASSWORD"),
    "database": "ml_boletos",
}

DISCORD_WEBHOOK = os.getenv("DISCORD_WEBHOOK")

# Níveis do mais grave para o menos grave. A regra é a mesma de
# src/boletos/nivel.php (tabela e Dash): mudou lá, mude aqui também.
# As cores são as do Tailwind usadas na tabela (red/orange/yellow-500).
NIVEIS = [
    {
        "id": "critico",
        "titulo": "🔴 CRÍTICO",
        "cor": 0xEF4444,
        "cargo": os.getenv("DISCORD_ROLE_CRITICO", ""),
    },
    {
        "id": "urgente",
        "titulo": "🟠 URGENTE",
        "cor": 0xF97316,
        "cargo": os.getenv("DISCORD_ROLE_URGENTE", ""),
    },
    {
        "id": "atencao",
        "titulo": "🟡 ATENÇÃO",
        "cor": 0xEAB308,
        "cargo": os.getenv("DISCORD_ROLE_ATENCAO", ""),
    },
]

# Limites do Discord: 4096 caracteres por embed e 6000 somando todos.
# 1700 por nível deixa folga para os três níveis, títulos e o embed do link.
LIMITE_DESCRICAO = 1700


def get_connection():
    return mysql.connector.connect(**MYSQL_CONFIG)


def calcular_vencimento_real(data_base, feriados):
    """Passa para o próximo dia útil se cair em fim de semana ou feriado."""
    data_atual = data_base

    while data_atual.weekday() >= 5 or data_atual in feriados:
        data_atual += timedelta(days=1)

    return data_atual


def classificar(agendado, dias):
    """Nível de alerta de um boleto não pago (None = não vai para o Discord)."""
    if dias < 0:
        return "critico"
    if not agendado and dias <= 3:
        return "urgente"
    if not agendado and dias <= 7:
        return "atencao"
    if agendado and dias == 0:
        return "atencao"
    return None


def buscar_dados():
    conn = get_connection()

    try:
        cursor = conn.cursor(dictionary=True)

        cursor.execute("""
            SELECT *
            FROM boletos
            WHERE deleted_at IS NULL
              AND pago = 0
        """)
        boletos = cursor.fetchall()

        cursor.execute("SELECT data FROM feriados")
        feriados = {row["data"] for row in cursor.fetchall()}

        return boletos, feriados

    finally:
        conn.close()


def formatar_valor(valor):
    if valor is None:
        return "Não informado"

    return (
        f"R$ {valor:,.2f}"
        .replace(",", "X")
        .replace(".", ",")
        .replace("X", ".")
    )


def texto_prazo(dias):
    if dias < 0:
        atraso = abs(dias)
        return f"venceu há {atraso} {'dia' if atraso == 1 else 'dias'}"
    if dias == 0:
        return "vence hoje"
    if dias == 1:
        return "vence amanhã"
    return f"vence em {dias} dias"


def texto_acao(nivel, agendado):
    if nivel == "critico":
        return "Confirme o pagamento." if agendado else "Não foi agendado: pague e marque como pago."
    if agendado:
        return "Pagamento agendado para hoje."
    return "É necessário agendar o pagamento."


def formatar_boleto(item):
    boleto = item["boleto"]
    original = boleto["data_vencimento"]
    real = item["vencimento_real"]

    vencimento = f"`{real:%d/%m/%Y}`"
    if real != original:
        vencimento += f" (cadastrado {original:%d/%m})"

    return (
        f"**{boleto['descricao']}** · `{formatar_valor(boleto['valor'])}`\n"
        f"{vencimento} · **{texto_prazo(item['dias'])}**\n"
        f"↳ {texto_acao(item['nivel'], item['agendado'])}"
    )


def montar_descricao(itens):
    """Junta os boletos sem passar do limite; o que sobrar vira 'e mais N'."""
    partes = []
    tamanho = 0

    for i, item in enumerate(itens):
        texto = formatar_boleto(item)
        resto = len(itens) - i
        reserva = len(f"\n\n… e mais {resto} no painel")

        if tamanho + len(texto) + 2 + reserva > LIMITE_DESCRICAO:
            partes.append(f"… e mais {resto} no painel")
            break

        partes.append(texto)
        tamanho += len(texto) + 2

    return "\n\n".join(partes)


def montar_payload(por_nivel, lembrete=False):
    presentes = [n for n in NIVEIS if por_nivel[n["id"]]]
    if not presentes:
        return None

    embeds = []
    for nivel in presentes:
        itens = por_nivel[nivel["id"]]
        total = sum(float(i["boleto"]["valor"] or 0) for i in itens)
        qtd = len(itens)

        embeds.append({
            "title": (
                f"{nivel['titulo']} — {qtd} {'boleto' if qtd == 1 else 'boletos'}"
                f" · {formatar_valor(total)}"
            ),
            "description": montar_descricao(itens),
            "color": nivel["cor"],
        })

    embeds.append({
        "title": "🔗 Gerenciar boletos",
        "description": LINK_BOLETOS,
        "url": LINK_BOLETOS,
    })

    # Menção em cascata: o pior nível do dia marca o cargo dele e os dos
    # níveis abaixo. Quem tem só @Urgente é avisado em dias de Urgente ou Crítico.
    pior = NIVEIS.index(presentes[0])
    cargos = [n["cargo"] for n in NIVEIS[pior:] if n["cargo"]]

    cabecalho = "⏰ **Lembrete: ainda há boleto crítico**" if lembrete else "💰 **Controle de boletos**"
    mencoes = " ".join(f"<@&{c}>" for c in cargos)

    return {
        "content": f"{cabecalho}\n{mencoes}".strip(),
        "embeds": embeds,
        "allowed_mentions": {"parse": [], "roles": cargos},
    }


def processar(lembrete=False, dry_run=False, limite=None):
    hoje = datetime.now(FUSO).date()

    boletos, feriados = buscar_dados()
    if limite:
        boletos = boletos[:limite]

    por_nivel = {n["id"]: [] for n in NIVEIS}

    for boleto in boletos:
        vencimento_real = calcular_vencimento_real(boleto["data_vencimento"], feriados)
        dias = (vencimento_real - hoje).days
        agendado = bool(boleto["agendado"])
        nivel = classificar(agendado, dias)

        if nivel:
            por_nivel[nivel].append({
                "boleto": boleto,
                "vencimento_real": vencimento_real,
                "dias": dias,
                "agendado": agendado,
                "nivel": nivel,
            })

    for itens in por_nivel.values():
        itens.sort(key=lambda i: i["dias"])

    resumo = ", ".join(f"{len(v)} {k}" for k, v in por_nivel.items())

    if lembrete and not por_nivel["critico"]:
        print(f"{datetime.now(FUSO):%Y-%m-%d %H:%M} lembrete: nenhum crítico, nada enviado ({resumo}).")
        return

    payload = montar_payload(por_nivel, lembrete)
    if payload is None:
        print(f"{datetime.now(FUSO):%Y-%m-%d %H:%M} nenhuma notificação necessária.")
        return

    if dry_run:
        print(json.dumps(payload, ensure_ascii=False, indent=2, default=str))
        return

    response = requests.post(DISCORD_WEBHOOK, json=payload, timeout=15)
    response.raise_for_status()

    tipo = "lembrete" if lembrete else "envio"
    print(f"{datetime.now(FUSO):%Y-%m-%d %H:%M} {tipo} Discord: {resumo}.")


if __name__ == "__main__":
    parser = argparse.ArgumentParser(description="Aviso dos boletos no Discord")
    parser.add_argument("--lembrete", action="store_true", help="só envia se houver boleto crítico")
    parser.add_argument("--dry-run", action="store_true", help="mostra o payload sem enviar")
    parser.add_argument("--limite", type=int, help="usa só os N primeiros boletos")
    args = parser.parse_args()

    processar(lembrete=args.lembrete, dry_run=args.dry_run, limite=args.limite)
