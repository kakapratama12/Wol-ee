"""Format pesan item tidak ditemukan + link dashboard."""

from __future__ import annotations

from config import config


def get_app_url() -> str:
    app_url = getattr(config, "WOL_EE_APP_URL", "") or ""
    if app_url:
        return app_url.rstrip("/")
    api_url = getattr(config, "WOL_EE_API_URL", "").rstrip("/")
    if api_url.endswith("/api"):
        return api_url[:-4]
    return api_url


def format_item_not_found(
    kind: str,
    search_name: str,
    available_items: list[str],
    dashboard_url: str | None = None,
) -> str:
    labels = {
        "product": ("Produk", "produk baru", "/products"),
        "ingredient": ("Bahan", "bahan baru", "/inventory"),
        "partner": ("Partner", "partner baru", "/partners"),
    }
    label, add_label, default_path = labels.get(kind, ("Item", "item baru", "/"))

    url = dashboard_url or f"{get_app_url()}{default_path}"
    lines = [f"❌ {label} '{search_name}' belum ada.\n"]

    if available_items:
        lines.append(f"{label} yang tersedia:")
        for name in available_items[:50]:
            lines.append(f"- {name}")
        lines.append("")
    else:
        lines.append(f"Belum ada {label.lower()} terdaftar.\n")

    lines.append(f"Tambah {add_label}: {url}")
    return "\n".join(lines)
