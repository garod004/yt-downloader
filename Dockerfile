FROM python:3.12-slim

# ffmpeg: necessário para mesclar vídeo+áudio e converter MP3
RUN apt-get update \
    && apt-get install -y --no-install-recommends ffmpeg \
    && rm -rf /var/lib/apt/lists/*

WORKDIR /app

# Instalar dependências primeiro (aproveita cache do Docker)
COPY requirements.txt .
RUN pip install --no-cache-dir -r requirements.txt

# Copiar código da aplicação
COPY app/ ./app/

# Criar pastas de dados (serão sobrescritas pelo volume em produção)
RUN mkdir -p /app/downloads /app/data

EXPOSE 8090

CMD ["sh", "-c", "pip install -q --upgrade yt-dlp 2>/dev/null || true && uvicorn app.main:app --host 0.0.0.0 --port 8090 --workers 1"]
