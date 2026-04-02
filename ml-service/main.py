# pyre-ignore-all-errors
"""
Provides ML training, prediction, and data quality endpoints
for the appointment system's decision support.
"""

import os
import sys

# Add parent directory to path for imports
sys.path.insert(0, os.path.dirname(__file__))

from fastapi import FastAPI, HTTPException, Header, Depends
from fastapi.middleware.cors import CORSMiddleware
from pydantic import BaseModel, Field
from typing import Optional
import logging

from config import ML_CONFIG, SERVER_CONFIG
from models.trainer import train as train_model, get_model_status
from models.features import (
    get_data_quality_report,
    get_completed_appointments_count,
    extract_single_appointment_features,
    get_staff_features,
    get_slot_features,
)
from models.predictor import (
    predict_risk,
    predict_staff_ranking,
    predict_slot_ranking,
)

logging.basicConfig(level=logging.INFO)
logger = logging.getLogger(__name__)

# Disable API docs in production to prevent reconnaissance
_is_prod = os.getenv("ENVIRONMENT", "development") == "production"
app = FastAPI(
    title="Appointment ML Service",
    description="ML training and prediction for appointment decision support",
    version="1.0.0",
    docs_url=None if _is_prod else "/docs",
    redoc_url=None if _is_prod else "/redoc",
    openapi_url=None if _is_prod else "/openapi.json",
)

app.add_middleware(
    CORSMiddleware,
    # SECURITY: Only allow the backend to make requests.
    # If the frontend never calls this directly, we can keep it strictly to localhost/127.0.0.1
    allow_origins=["http://localhost", "http://127.0.0.1", "http://localhost:8000"],
    allow_methods=["GET", "POST"],
    allow_headers=["Content-Type", "X-API-Key", "Authorization"],
)


# ─── Auth Dependency ──────────────────────────────────────────────────────────

async def verify_api_key(x_api_key: Optional[str] = Header(None)):
    """Verify the API key. Rejects all requests if API key is not configured."""
    import hmac as _hmac
    expected = ML_CONFIG.get('api_key', '')
    if not expected:
        raise HTTPException(status_code=503, detail="Service not configured: API key is missing")
    if not x_api_key or not _hmac.compare_digest(x_api_key, expected):
        raise HTTPException(status_code=401, detail="Invalid API key")


# ─── Request Models ───────────────────────────────────────────────────────────

class PredictRiskRequest(BaseModel):
    appointment_id: int


class PredictSlotRequest(BaseModel):
    date: str


class FeedbackRequest(BaseModel):
    appointment_id: int
    actual_outcome: str = Field(..., pattern='^(completed|cancelled|no_show)$')
    staff_feedback: Optional[str] = Field(None, pattern='^(accepted|rejected|overridden)$')
    feedback_reason: Optional[str] = Field(None, max_length=1000)

class ChatMessage(BaseModel):
    role: str
    content: str
    
class GroqFallbackRequest(BaseModel):
    messages: list[ChatMessage]
    temperature: Optional[float] = 0.7


# ─── Endpoints ────────────────────────────────────────────────────────────────

@app.get("/health")
async def health_check():
    """Health check endpoint."""
    return {"status": "healthy", "service": "ml-service"}


@app.get("/status", dependencies=[Depends(verify_api_key)])
async def model_status():
    """Get current model status and metadata."""
    try:
        status = get_model_status()
        data_quality = get_data_quality_report()
        return {
            "status": "ok",
            "model": status,
            "data_quality": data_quality,
        }
    except Exception as e:
        logger.error(f"Error getting status: {e}")
        raise HTTPException(status_code=500, detail=str(e))


@app.get("/data-quality", dependencies=[Depends(verify_api_key)])
async def data_quality():
    """Get data quality report for training readiness assessment."""
    try:
        report = get_data_quality_report()
        model = get_model_status()
        return {
            "status": "ok",
            "data": report,
            "model_status": model,
            "training_ready": report['is_sufficient'] and report['class_balance']['is_balanced'],
        }
    except Exception as e:
        logger.error(f"Error getting data quality: {e}")
        raise HTTPException(status_code=500, detail=str(e))


@app.post("/train", dependencies=[Depends(verify_api_key)])
async def trigger_training():
    """
    Trigger ML model training.
    Returns metrics if successful, or insufficient-data warning if not enough records.
    """
    try:
        logger.info("Training triggered")
        result = train_model()
        logger.info(f"Training result: {result.get('status')}")
        return {"status": "ok", "data": result}
    except Exception as e:
        logger.error(f"Training error: {e}", exc_info=True)
        raise HTTPException(status_code=500, detail=f"Training failed: {str(e)}")


@app.post("/predict/risk", dependencies=[Depends(verify_api_key)])
async def predict_appointment_risk(request: PredictRiskRequest):
    """Predict cancellation/no-show risk for an appointment."""
    try:
        features = extract_single_appointment_features(request.appointment_id)
        if not features:
            raise HTTPException(status_code=404, detail="Appointment not found")

        result = predict_risk(features)

        if result.get('status') == 'no_model':
            return {
                "status": "no_model",
                "message": result['message'],
                "data_quality": get_data_quality_report(),
            }

        return {"status": "ok", "data": result}
    except HTTPException:
        raise
    except Exception as e:
        logger.error(f"Prediction error: {e}", exc_info=True)
        raise HTTPException(status_code=500, detail=str(e))


@app.post("/predict/slot-rank", dependencies=[Depends(verify_api_key)])
async def predict_slot_ranking_endpoint(request: PredictSlotRequest):
    """Rank time slots for a given date by predicted success."""
    try:
        slots = get_slot_features(request.date)
        if not slots:
            return {"status": "ok", "data": [], "message": "No slots available"}

        ranked = predict_slot_ranking(slots)
        return {
            "status": "ok",
            "data": ranked,
            "total_slots": len(slots),
            "available_slots": len([s for s in ranked if s.get('status') != 'full']),
        }
    except Exception as e:
        logger.error(f"Slot ranking error: {e}", exc_info=True)
        raise HTTPException(status_code=500, detail=str(e))


@app.post("/feedback", dependencies=[Depends(verify_api_key)])
async def log_feedback(request: FeedbackRequest):
    """Log outcome feedback for future retraining."""
    try:
        # Store feedback in a local JSON file for batch processing
        import json
        from datetime import datetime, timezone

        feedback_dir = os.path.join(os.path.dirname(__file__), 'storage', 'feedback')
        os.makedirs(feedback_dir, exist_ok=True)

        feedback_file = os.path.join(feedback_dir, 'outcomes.jsonl')
        entry = {
            'appointment_id': request.appointment_id,
            'actual_outcome': request.actual_outcome,
            'staff_feedback': request.staff_feedback,
            'feedback_reason': request.feedback_reason,
            'timestamp': datetime.now(timezone.utc).isoformat().replace('+00:00', 'Z'),
        }

        with open(feedback_file, 'a') as f:
            f.write(json.dumps(entry) + '\n')

        return {"status": "ok", "message": "Feedback logged successfully"}
    except Exception as e:
        logger.error(f"Feedback error: {e}")
        raise HTTPException(status_code=500, detail=str(e))


@app.post("/chat/fallback", dependencies=[Depends(verify_api_key)])
async def chat_fallback_groq(request: GroqFallbackRequest):
    """
    Final Fallback Chat completion utilizing Groq API 
    Model: llama-3.3-70b-versatile
    """
    import groq
    
    api_key = os.getenv("GROQ_API_KEY")
    # Default to llama-3.3-70b-versatile per architecture if not defined
    model = os.getenv("GROQ_MODEL", "llama-3.3-70b-versatile")
    
    if not api_key:
        logger.error("Groq API key not configured")
        raise HTTPException(status_code=500, detail="Groq API key missing in environment")
        
    try:
        client = groq.Groq(api_key=api_key)
        
        formatted_messages = [{"role": msg.role, "content": msg.content} for msg in request.messages]
        
        response = client.chat.completions.create(
            messages=formatted_messages,
            model=model,
            temperature=request.temperature,
            max_tokens=2048,
        )
        
        return {
            "status": "ok",
            "model_used": model,
            "response": response.choices[0].message.content,
        }
    except groq.APIError as e:
        logger.error(f"Groq API Error: {e.message}")
        raise HTTPException(status_code=502, detail=f"Target Groq API failed: {e.message}")
    except Exception as e:
        logger.error(f"Groq fallback processing error: {e}", exc_info=True)
        raise HTTPException(status_code=500, detail=f"Final fallback completely failed: {str(e)}")


# ─── Run Server ───────────────────────────────────────────────────────────────

if __name__ == "__main__":
    import uvicorn
    is_prod = os.getenv("ENVIRONMENT", "development") == "production"
    uvicorn.run(
        "main:app",
        host=SERVER_CONFIG['host'],
        port=SERVER_CONFIG['port'],
        reload=not is_prod,
        log_level="info",
    )
