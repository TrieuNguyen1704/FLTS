from fastapi import FastAPI

app = FastAPI(title="FLTS AI Service", version="0.1.0")


@app.get("/health")
def health() -> dict[str, str]:
    return {
        "status": "ok",
        "service": "fastapi",
        "scope": "placeholder only; document extraction and RAG are Sprint 2 work",
    }
