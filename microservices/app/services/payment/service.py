"""
Payment Service Implementation
Integrates with Paystack/Flutterwave for payment processing
"""

from typing import Dict, Any, Optional
import httpx
import hashlib
import hmac
from app.config import settings
from app.redis_client import get_redis
import logging
import json
from datetime import datetime, timezone

logger = logging.getLogger(__name__)


class PaymentService:
    """Payment gateway service for Paystack/Flutterwave integration"""
    
    def __init__(self):
        self.paystack_base_url = settings.paystack_base_url
        self.paystack_secret_key = settings.paystack_secret_key
        self.paystack_webhook_secret = settings.paystack_webhook_secret
    
    async def initialize_payment(
        self,
        amount: float,
        email: str,
        metadata: Dict[str, Any]
    ) -> Dict[str, Any]:
        """
        Initialize payment with Paystack
        
        Args:
            amount: Payment amount in kobo (multiply by 100)
            email: User email
            metadata: Additional payment metadata
        
        Returns:
            Dict containing authorization_url and reference
        """
        try:
            url = f"{self.paystack_base_url}/transaction/initialize"
            headers = {
                "Authorization": f"Bearer {self.paystack_secret_key}",
                "Content-Type": "application/json"
            }
            
            payload = {
                "amount": int(amount * 100),  # Convert to kobo
                "email": email,
                "metadata": metadata,
                "callback_url": f"{settings.laravel_api_url}/webhooks/paystack/callback"
            }
            
            async with httpx.AsyncClient() as client:
                response = await client.post(url, json=payload, headers=headers)
                response.raise_for_status()
                
                data = response.json()
                
                if data.get("status"):
                    logger.info(f"Payment initialized: {data['data']['reference']}")
                    return {
                        "authorization_url": data["data"]["authorization_url"],
                        "access_code": data["data"]["access_code"],
                        "reference": data["data"]["reference"]
                    }
                else:
                    logger.error(f"Payment initialization failed: {data.get('message')}")
                    raise Exception(data.get("message", "Payment initialization failed"))
        
        except httpx.HTTPError as e:
            logger.error(f"HTTP error during payment initialization: {e}")
            raise
        except Exception as e:
            logger.error(f"Error initializing payment: {e}")
            raise
    
    async def verify_payment(self, reference: str) -> Dict[str, Any]:
        """
        Verify payment status with Paystack
        
        Args:
            reference: Payment reference
        
        Returns:
            Dict containing payment details
        """
        try:
            url = f"{self.paystack_base_url}/transaction/verify/{reference}"
            headers = {
                "Authorization": f"Bearer {self.paystack_secret_key}"
            }
            
            async with httpx.AsyncClient() as client:
                response = await client.get(url, headers=headers)
                response.raise_for_status()
                
                data = response.json()
                
                if data.get("status"):
                    logger.info(f"Payment verified: {reference}")
                    return data["data"]
                else:
                    logger.error(f"Payment verification failed: {data.get('message')}")
                    raise Exception(data.get("message", "Payment verification failed"))
        
        except httpx.HTTPError as e:
            logger.error(f"HTTP error during payment verification: {e}")
            raise
        except Exception as e:
            logger.error(f"Error verifying payment: {e}")
            raise
    
    async def initiate_payout(
        self,
        amount: float,
        account_number: str,
        bank_code: str,
        reason: str = "Payout"
    ) -> Dict[str, Any]:
        """
        Initiate payout to bank account
        
        Args:
            amount: Payout amount in naira
            account_number: Recipient account number
            bank_code: Bank code
            reason: Payout reason/description
        
        Returns:
            Dict containing payout reference and status
        """
        try:
            url = f"{self.paystack_base_url}/transfer"
            headers = {
                "Authorization": f"Bearer {self.paystack_secret_key}",
                "Content-Type": "application/json"
            }
            
            # First, create transfer recipient
            recipient = await self._create_transfer_recipient(
                account_number, bank_code
            )
            
            payload = {
                "source": "balance",
                "amount": int(amount * 100),  # Convert to kobo
                "recipient": recipient["recipient_code"],
                "reason": reason
            }
            
            async with httpx.AsyncClient() as client:
                response = await client.post(url, json=payload, headers=headers)
                response.raise_for_status()
                
                data = response.json()
                
                if data.get("status"):
                    logger.info(f"Payout initiated: {data['data']['reference']}")
                    return {
                        "reference": data["data"]["reference"],
                        "transfer_code": data["data"]["transfer_code"],
                        "status": data["data"]["status"]
                    }
                else:
                    logger.error(f"Payout initiation failed: {data.get('message')}")
                    raise Exception(data.get("message", "Payout initiation failed"))
        
        except httpx.HTTPError as e:
            logger.error(f"HTTP error during payout initiation: {e}")
            raise
        except Exception as e:
            logger.error(f"Error initiating payout: {e}")
            raise
    
    async def _create_transfer_recipient(
        self,
        account_number: str,
        bank_code: str
    ) -> Dict[str, Any]:
        """Create transfer recipient for payout"""
        try:
            url = f"{self.paystack_base_url}/transferrecipient"
            headers = {
                "Authorization": f"Bearer {self.paystack_secret_key}",
                "Content-Type": "application/json"
            }
            
            payload = {
                "type": "nuban",
                "name": "Recipient",
                "account_number": account_number,
                "bank_code": bank_code,
                "currency": "NGN"
            }
            
            async with httpx.AsyncClient() as client:
                response = await client.post(url, json=payload, headers=headers)
                response.raise_for_status()
                
                data = response.json()
                
                if data.get("status"):
                    return data["data"]
                else:
                    raise Exception(data.get("message", "Failed to create recipient"))
        
        except Exception as e:
            logger.error(f"Error creating transfer recipient: {e}")
            raise
    
    async def resolve_account_number(
        self,
        account_number: str,
        bank_code: str
    ) -> Dict[str, Any]:
        """
        Resolve bank account number to get account name
        
        Args:
            account_number: Account number
            bank_code: Bank code
        
        Returns:
            Dict containing account_name and account_number
        """
        try:
            url = f"{self.paystack_base_url}/bank/resolve"
            headers = {
                "Authorization": f"Bearer {self.paystack_secret_key}"
            }
            
            params = {
                "account_number": account_number,
                "bank_code": bank_code
            }
            
            async with httpx.AsyncClient() as client:
                response = await client.get(url, headers=headers, params=params)
                response.raise_for_status()
                
                data = response.json()
                
                if data.get("status"):
                    logger.info(f"Account resolved: {account_number}")
                    return data["data"]
                else:
                    logger.error(f"Account resolution failed: {data.get('message')}")
                    raise Exception(data.get("message", "Account resolution failed"))
        
        except httpx.HTTPError as e:
            logger.error(f"HTTP error during account resolution: {e}")
            raise
        except Exception as e:
            logger.error(f"Error resolving account: {e}")
            raise
    
    async def list_banks(self) -> list:
        """
        Get list of supported banks
        
        Returns:
            List of banks with name and code
        """
        try:
            url = f"{self.paystack_base_url}/bank"
            headers = {
                "Authorization": f"Bearer {self.paystack_secret_key}"
            }
            
            async with httpx.AsyncClient() as client:
                response = await client.get(url, headers=headers)
                response.raise_for_status()
                
                data = response.json()
                
                if data.get("status"):
                    return data["data"]
                else:
                    raise Exception(data.get("message", "Failed to fetch banks"))
        
        except Exception as e:
            logger.error(f"Error fetching banks: {e}")
            raise
    
    def verify_webhook_signature(self, payload: bytes, signature: str) -> bool:
        """
        Verify Paystack webhook signature
        
        Args:
            payload: Raw request body
            signature: X-Paystack-Signature header value
        
        Returns:
            True if signature is valid, False otherwise
        """
        try:
            computed_signature = hmac.new(
                self.paystack_webhook_secret.encode('utf-8'),
                payload,
                hashlib.sha512
            ).hexdigest()
            
            return hmac.compare_digest(computed_signature, signature)
        
        except Exception as e:
            logger.error(f"Error verifying webhook signature: {e}")
            return False
    
    async def process_webhook_idempotent(
        self,
        event: str,
        reference: str,
        payload: Dict[str, Any]
    ) -> Dict[str, Any]:
        """
        Process webhook with idempotency guarantee (Property 8)
        
        Args:
            event: Webhook event type
            reference: Payment reference (idempotency key)
            payload: Webhook payload
        
        Returns:
            Dict containing processing result
        """
        redis = await get_redis()
        webhook_key = f"webhook:processed:{reference}"
        
        try:
            # Check if webhook already processed
            existing = await redis.get(webhook_key)
            if existing:
                logger.info(f"Webhook already processed: {reference}")
                return json.loads(existing)
            
            # Process webhook
            result = await self._forward_to_laravel(event, reference, payload)
            
            # Store result with 24-hour expiry (idempotency window)
            await redis.setex(
                webhook_key,
                86400,  # 24 hours
                json.dumps(result)
            )
            
            logger.info(f"Webhook processed successfully: {reference}")
            return result
        
        except Exception as e:
            logger.error(f"Error processing webhook: {e}")
            raise
    
    async def _forward_to_laravel(
        self,
        event: str,
        reference: str,
        payload: Dict[str, Any]
    ) -> Dict[str, Any]:
        """
        Forward webhook to Laravel backend
        
        Args:
            event: Webhook event type
            reference: Payment reference
            payload: Webhook payload
        
        Returns:
            Dict containing Laravel response
        """
        try:
            url = f"{settings.laravel_api_url}/webhooks/payment"
            headers = {
                "Authorization": f"Bearer {settings.laravel_api_key}",
                "Content-Type": "application/json"
            }
            
            forward_payload = {
                "event": event,
                "reference": reference,
                "data": payload,
                "timestamp": datetime.now(timezone.utc).isoformat()
            }
            
            async with httpx.AsyncClient(timeout=30.0) as client:
                response = await client.post(
                    url,
                    json=forward_payload,
                    headers=headers
                )
                response.raise_for_status()
                
                result = response.json()
                logger.info(f"Webhook forwarded to Laravel: {reference}")
                return result
        
        except httpx.HTTPError as e:
            logger.error(f"HTTP error forwarding webhook to Laravel: {e}")
            raise
        except Exception as e:
            logger.error(f"Error forwarding webhook to Laravel: {e}")
            raise
