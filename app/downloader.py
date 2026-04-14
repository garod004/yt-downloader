"""
Wrapper sobre yt-dlp: gerencia downloads em threads de background
e expõe progresso via dicionário em memória (progress_store).

DECISÃO ARQUITETURAL [2026-04-13]: Threading com dicionário em memória.
Motivo: baixa concorrência esperada (uso familiar). Sem necessidade de
Celery/Redis. Revisitar se volume de usuários simultâneos crescer.
"""

import os
import threading
from datetime import datetime
from typing import Any

import yt_dlp

DOWNLOAD_DIR = os.getenv("DOWNLOAD_DIR", "/app/downloads")

# Mapa em memória: {download_id: {status, percent, speed, eta, title, ...}}
# Lido pela rota SSE /api/progress/{id}
progress_store: dict[str, dict[str, Any]] = {}

# ─── Opções de formato disponíveis ────────────────────────────────────────────

FORMAT_OPTIONS: dict[str, dict] = {
    "video-1080": {
        "label": "Vídeo 1080p (MP4)",
        "format": "bestvideo[height<=1080]+bestaudio/best[height<=1080]/best",
        "merge_output_format": "mp4",
    },
    "video-720": {
        "label": "Vídeo 720p (MP4)",
        "format": "bestvideo[height<=720]+bestaudio/best[height<=720]/best",
        "merge_output_format": "mp4",
    },
    "video-480": {
        "label": "Vídeo 480p (MP4)",
        "format": "bestvideo[height<=480]+bestaudio/best[height<=480]/best",
        "merge_output_format": "mp4",
    },
    "audio-mp3": {
        "label": "Áudio MP3",
        "format": "bestaudio/best",
        "postprocessors": [
            {
                "key": "FFmpegExtractAudio",
                "preferredcodec": "mp3",
                "preferredquality": "192",
            }
        ],
    },
}


def get_format_label(format_key: str) -> str:
    return FORMAT_OPTIONS.get(format_key, {}).get("label", format_key)


# ─── Lógica de download (roda em thread separada) ─────────────────────────────

def _run_download(download_id: str, url: str, format_key: str) -> None:
    """Executa o download e atualiza progress_store e banco de dados."""
    from app.database import update_download  # import local para evitar circular

    os.makedirs(DOWNLOAD_DIR, exist_ok=True)

    # Estado inicial
    progress_store[download_id] = {
        "status": "downloading",
        "percent": 0.0,
        "speed": "",
        "eta": "",
        "title": "Iniciando...",
        "filename": "",
        "error": None,
        "playlist_index": 0,
        "playlist_total": 0,
    }

    fmt = FORMAT_OPTIONS.get(format_key, FORMAT_OPTIONS["video-720"])

    def progress_hook(d: dict) -> None:
        store = progress_store[download_id]

        if d["status"] == "downloading":
            percent_raw = d.get("_percent_str", "0%").strip().replace("%", "")
            try:
                percent = float(percent_raw)
            except ValueError:
                percent = 0.0

            info = d.get("info_dict", {})
            store.update(
                {
                    "status": "downloading",
                    "percent": round(percent, 1),
                    "speed": d.get("_speed_str", "").strip(),
                    "eta": d.get("_eta_str", "").strip(),
                    "title": info.get("title", store["title"]),
                    "playlist_index": info.get("playlist_index") or 0,
                    "playlist_total": info.get("n_entries") or 0,
                }
            )

        elif d["status"] == "finished":
            info = d.get("info_dict", {})
            filename = os.path.basename(d.get("filename", ""))
            store.update(
                {
                    "percent": 99.0,  # conversão ffmpeg ainda pode ocorrer
                    "filename": filename,
                    "title": info.get("title", store["title"]),
                }
            )

    # Montar opções do yt-dlp
    ydl_opts: dict[str, Any] = {
        "outtmpl": os.path.join(DOWNLOAD_DIR, "%(title)s.%(ext)s"),
        "progress_hooks": [progress_hook],
        "quiet": True,
        "no_warnings": True,
        "format": fmt["format"],
        "nocheckcertificate": True,
        "ignoreerrors": False,
        "geo_bypass": True,
        "extractor_retries": 3,
        "http_headers": {
            "User-Agent": (
                "Mozilla/5.0 (Windows NT 10.0; Win64; x64) "
                "AppleWebKit/537.36 (KHTML, like Gecko) "
                "Chrome/120.0.0.0 Safari/537.36"
            )
        },
    }
    if "merge_output_format" in fmt:
        ydl_opts["merge_output_format"] = fmt["merge_output_format"]
    if "postprocessors" in fmt:
        ydl_opts["postprocessors"] = fmt["postprocessors"]

    try:
        with yt_dlp.YoutubeDL(ydl_opts) as ydl:
            info = ydl.extract_info(url, download=True)

        title: str = info.get("title", "") if info else ""
        filename = progress_store[download_id].get("filename", "")
        is_playlist = 1 if info and info.get("_type") == "playlist" else 0
        playlist_total = info.get("n_entries", 0) if info else 0

        progress_store[download_id].update(
            {
                "status": "complete",
                "percent": 100.0,
                "title": title,
                "speed": "",
                "eta": "",
            }
        )
        update_download(
            download_id,
            title=title,
            status="complete",
            filename=filename,
            is_playlist=is_playlist,
            playlist_total=playlist_total,
            completed_at=datetime.now().isoformat(),
        )

    except Exception as exc:
        error_msg = str(exc)
        progress_store[download_id].update(
            {"status": "error", "error": error_msg}
        )
        update_download(download_id, status="error", error_msg=error_msg)


def start_download(download_id: str, url: str, format_key: str) -> None:
    """Inicia o download em uma thread daemon de background."""
    thread = threading.Thread(
        target=_run_download,
        args=(download_id, url, format_key),
        daemon=True,
        name=f"dl-{download_id[:8]}",
    )
    thread.start()
