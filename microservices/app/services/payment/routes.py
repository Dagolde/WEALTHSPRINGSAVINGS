"""
Payment Service API Routes
Endpoints for payment processing
"""

from fastapi import APIRouter, HTTPException, Request, Header
from pydantic import BaseModel, EmailStr, Field
from typing import Dict, Any, Optional
from app.services.payment.service import PaymentService
import logging

logger = logging.getLogger(__name__)

router = APIRouter()
payment_service = PaymentService()


class PaymentInitRequest(BaseModel):
    """Payment initialization request"""
    amount: float = Field(gt=0, description="Payment amount (must be positive)")
    email: EmailStr
    metadata: Dict[str, Any] = {}


class PayoutRequest(BaseModel):
    """Payout request"""
    amount: float = Field(gt=0, description="Payout amount (must be positive)")
    account_number: str = Field(min_length=10, max_length=10, description="10-digit account number")
    bank_code: str
    reason: str = "Payout"


class AccountResolveRequest(BaseModel):
    """Account resolution request"""
    account_number: str = Field(min_length=10, max_length=10, description="10-digit account number")
    bank_code: str


@router.post("/initialize")
async def initialize_payment(request: PaymentInitRequest):
    """Initialize payment transaction"""
    try:
        result = await payment_service.initialize_payment(
            amount=request.amount,
            email=request.email,
            metadata=request.metadata
        )
        return {
            "success": True,
            "data": result
        }
    except Exception as e:
        logger.error(f"Payment initialization error: {e}")
        raise HTTPException(status_code=500, detail=str(e))


@router.get("/verify/{reference}")
async def verify_payment(reference: str):
    """Verify payment transaction"""
    try:
        result = await payment_service.verify_payment(reference)
        return {
            "success": True,
            "data": result
        }
    except Exception as e:
        logger.error(f"Payment verification error: {e}")
        raise HTTPException(status_code=500, detail=str(e))


@router.post("/payout")
async def initiate_payout(request: PayoutRequest):
    """Initiate payout to bank account"""
    try:
        result = await payment_service.initiate_payout(
            amount=request.amount,
            account_number=request.account_number,
            bank_code=request.bank_code,
            reason=request.reason
        )
        return {
            "success": True,
            "data": result
        }
    except Exception as e:
        logger.error(f"Payout initiation error: {e}")
        raise HTTPException(status_code=500, detail=str(e))


@router.post("/resolve-account")
async def resolve_account(request: AccountResolveRequest):
    """Resolve bank account number"""
    try:
        result = await payment_service.resolve_account_number(
            account_number=request.account_number,
            bank_code=request.bank_code
        )
        return {
            "success": True,
            "data": result
        }
    except Exception as e:
        logger.error(f"Account resolution error: {e}")
        raise HTTPException(status_code=500, detail=str(e))


@router.get("/banks")
async def list_banks():
    """Get list of supported banks"""
    try:
        result = await payment_service.list_banks()
        return {
            "success": True,
            "data": result
        }
    except Exception as e:
        logger.error(f"Error fetching banks: {e}")
        raise HTTPException(status_code=500, detail=str(e))


@router.post("/webhook")
async def handle_webhook(
    request: Request,
    x_paystack_signature: Optional[str] = Header(None)
):
    """
    Handle Paystack webhook with idempotent processing
    
    Implements Property 8: Payment Webhook Idempotency
    For any payment reference, processing the webhook multiple times 
    should result in the same final state as processing it once.
    """
    try:
        # Get raw body
        body = await request.body()
        
        # Verify signature
        if not x_paystack_signature:
            logger.warning("Missing webhook signature")
            raise HTTPException(status_code=401, detail="Missing signature")
        
        if not payment_service.verify_webhook_signature(body, x_paystack_signature):
            logger.warning("Invalid webhook signature")
            raise HTTPException(status_code=401, detail="Invalid signature")
        
        # Parse payload
        payload = await request.json()
        
        # Extract event and reference
        event = payload.get('event')
        reference = payload.get('data', {}).get('reference')
        
        if not event or not reference:
            logger.error("Missing event or reference in webhook payload")
            raise HTTPException(status_code=400, detail="Invalid webhook payload")
        
        # Log webhook event
        logger.info(f"Webhook received: {event} - {reference}")
        
        # Process webhook with idempotency
        result = await payment_service.process_webhook_idempotent(
            event=event,
            reference=reference,
            payload=payload
        )
        
        return {
            "success": True,
            "data": result
        }
    
    except HTTPException:
        raise
    except Exception as e:
        logger.error(f"Webhook processing error: {e}")
        raise HTTPException(status_code=500, detail=str(e))
