"""Konfigurasi bot Wol-ee. Salin ke config.py dan sesuaikan."""

WOL_EE_API_URL = "https://your-domain.com/api"
WOL_EE_API_TOKEN = "1:abc123def456ghi789"

# Timeout HTTP ke API Laravel (detik)
API_TIMEOUT = 15

# Path penyimpanan lokal bot
BOT_DATA_DIR = "./data"

# URL dashboard (tanpa /api) untuk link tambah produk/bahan/partner
WOL_EE_APP_URL = "https://your-domain.com"

# LLM provider untuk parsing natural language
GROQ_API_KEY = ""
GROQ_BASE_URL = "https://api.groq.com/openai/v1"
GROQ_MODEL = "llama-3.1-8b-instant"

OPENROUTER_API_KEY = ""
OPENROUTER_BASE_URL = "https://openrouter.ai/api/v1"
OPENROUTER_MODEL = "deepseek/deepseek-chat"
