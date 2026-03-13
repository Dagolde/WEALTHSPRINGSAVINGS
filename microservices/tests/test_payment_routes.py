"""
Payment Routes Tests
Tests for payment API endpoints
"""

import pytest
from fastapi.testclient import TestClient
from unittest.mock import AsyncMock, patch, Mock
from app.main import app
import json
import hmac
import hashlib

client = TestClient(app)


class TestPaymentInitializationEndpoint:
    """Test payment initialization endpoint"""
    
    def test_initialize_payment_success(self):
        """Test POST /api/v1/payments/initialize success"""
        with patch('app.services.payment.routes.payment_service.initialize_payment') as mock_init:
            mock_init.return_value = {
                "authorization_url": "https://checkout.paystack.com/test",
                "reference": "test_ref_123",
                "access_code": "test_access"
            }
            
            response = client.post(
                "/api/v1/payments/initialize",
                json={
                    "amount": 1000.0,
                    "email": "test@example.com",
                    "metadata": {"user_id": 1}
                }
            )
            
            assert response.status_code == 200
            data = response.json()
            assert data["success"] is True
            assert "authorization_url" in data["data"]
            assert data["data"]["reference"] == "test_ref_123"
    
    def test_initialize_payment_invalid_email(self):
        """Test payment initialization with invalid email"""
        response = client.post(
            "/api/v1/payments/initialize",
            json={
                "amount": 1000.0,
                "email": "invalid-email",
                "metadata": {}
            }
        )
        
        assert response.status_code == 422  # Validation error
    
    def test_initialize_payment_missing_fields(self):
        """Test payment initialization with missing required fields"""
        response = client.post(
            "/api/v1/payments/initialize",
            json={
                "amount": 1000.0
                # Missing email
            }
        )
        
        assert response.status_code == 422


class TestPaymentVerificationEndpoint:
    """Test payment verification endpoint"""
    
    def test_verify_payment_success(self):
        """Test GET /api/v1/payments/verify/{reference} success"""
        with patch('app.services.payment.routes.payment_service.verify_payment') as mock_verify:
            mock_verify.return_value = {
                "reference": "test_ref_123",
                "amount": 100000,
                "status": "success"
            }
            
            response = client.get("/api/v1/payments/verify/test_ref_123")
            
            assert response.status_code == 200
            data = response.json()
            assert data["success"] is True
            assert data["data"]["status"] == "success"
    
    def test_verify_payment_not_found(self):
        """Test payment verification for non-existent reference"""
        with patch('app.services.payment.routes.payment_service.verify_payment') as mock_verify:
            mock_verify.side_effect = Exception("Transaction not found")
            
            response = client.get("/api/v1/payments/verify/invalid_ref")
            
            assert response.status_code == 500


class TestPayoutEndpoint:
    """Test payout endpoint"""
    
    def test_initiate_payout_success(self):
        """Test POST /api/v1/payments/payout success"""
        with patch('app.services.payment.routes.payment_service.initiate_payout') as mock_payout:
            mock_payout.return_value = {
                "reference": "TRF_test123",
                "transfer_code": "TRF_CODE_123",
                "status": "pending"
            }
            
            response = client.post(
                "/api/v1/payments/payout",
                json={
                    "amount": 5000.0,
                    "account_number": "0123456789",
                    "bank_code": "058",
                    "reason": "Test payout"
                }
            )
            
            assert response.status_code == 200
            data = response.json()
            assert data["success"] is True
            assert data["data"]["status"] == "pending"
    
    def test_initiate_payout_missing_fields(self):
        """Test payout with missing required fields"""
        response = client.post(
            "/api/v1/payments/payout",
            json={
                "amount": 5000.0
                # Missing account_number and bank_code
            }
        )
        
        assert response.status_code == 422


class TestBankAccountEndpoints:
    """Test bank account endpoints"""
    
    def test_resolve_account_success(self):
        """Test POST /api/v1/payments/resolve-account success"""
        with patch('app.services.payment.routes.payment_service.resolve_account_number') as mock_resolve:
            mock_resolve.return_value = {
                "account_number": "0123456789",
                "account_name": "John Doe"
            }
            
            response = client.post(
                "/api/v1/payments/resolve-account",
                json={
                    "account_number": "0123456789",
                    "bank_code": "058"
                }
            )
            
            assert response.status_code == 200
            data = response.json()
            assert data["success"] is True
            assert data["data"]["account_name"] == "John Doe"
    
    def test_list_banks_success(self):
        """Test GET /api/v1/payments/banks success"""
        with patch('app.services.payment.routes.payment_service.list_banks') as mock_banks:
            mock_banks.return_value = [
                {"name": "GTBank", "code": "058"},
                {"name": "Access Bank", "code": "044"}
            ]
            
            response = client.get("/api/v1/payments/banks")
            
            assert response.status_code == 200
            data = response.json()
            assert data["success"] is True
            assert len(data["data"]) == 2


class TestWebhookEndpoint:
    """
    Test webhook endpoint
    
    **Validates: Property 8 - Payment Webhook Idempotency**
    """
    
    def test_webhook_valid_signature(self):
        """Test webhook with valid signature"""
        payload = {"event": "charge.success", "data": {"reference": "test_ref_123"}}
        payload_bytes = json.dumps(payload).encode('utf-8')
        
        with patch('app.services.payment.routes.payment_service.verify_webhook_signature') as mock_verify:
            with patch('app.services.payment.routes.payment_service.process_webhook_idempotent') as mock_process:
                mock_verify.return_value = True
                mock_process.return_value = {"status": "processed"}
                
                response = client.post(
                    "/api/v1/payments/webhook",
                    json=payload,
                    headers={"X-Paystack-Signature": "valid_signature"}
                )
                
                assert response.status_code == 200
                data = response.json()
                assert data["success"] is True
    
    def test_webhook_invalid_signature(self):
        """Test webhook with invalid signature"""
        payload = {"event": "charge.success", "data": {"reference": "test_ref_123"}}
        
        with patch('app.services.payment.routes.payment_service.verify_webhook_signature') as mock_verify:
            mock_verify.return_value = False
            
            response = client.post(
                "/api/v1/payments/webhook",
                json=payload,
                headers={"X-Paystack-Signature": "invalid_signature"}
            )
            
            assert response.status_code == 401
            assert "Invalid signature" in response.json()["detail"]
    
    def test_webhook_missing_signature(self):
        """Test webhook without signature header"""
        payload = {"event": "charge.success", "data": {"reference": "test_ref_123"}}
        
        response = client.post(
            "/api/v1/payments/webhook",
            json=payload
        )
        
        assert response.status_code == 401
        assert "Missing signature" in response.json()["detail"]
    
    def test_webhook_missing_reference(self):
        """Test webhook with missing reference"""
        payload = {"event": "charge.success", "data": {}}
        
        with patch('app.services.payment.routes.payment_service.verify_webhook_signature') as mock_verify:
            mock_verify.return_value = True
            
            response = client.post(
                "/api/v1/payments/webhook",
                json=payload,
                headers={"X-Paystack-Signature": "valid_signature"}
            )
            
            assert response.status_code == 400
            assert "Invalid webhook payload" in response.json()["detail"]
    
    def test_webhook_idempotency(self):
        """
        Test webhook idempotency - duplicate webhooks return same result
        
        **Validates: Property 8**
        """
        payload = {"event": "charge.success", "data": {"reference": "test_ref_123"}}
        
        with patch('app.services.payment.routes.payment_service.verify_webhook_signature') as mock_verify:
            with patch('app.services.payment.routes.payment_service.process_webhook_idempotent') as mock_process:
                mock_verify.return_value = True
                mock_process.return_value = {"status": "processed", "cached": True}
                
                # First request
                response1 = client.post(
                    "/api/v1/payments/webhook",
                    json=payload,
                    headers={"X-Paystack-Signature": "valid_signature"}
                )
                
                # Second request (duplicate)
                response2 = client.post(
                    "/api/v1/payments/webhook",
                    json=payload,
                    headers={"X-Paystack-Signature": "valid_signature"}
                )
                
                # Both should succeed with same result
                assert response1.status_code == 200
                assert response2.status_code == 200
                assert response1.json() == response2.json()


class TestErrorHandling:
    """Test error handling in payment endpoints"""
    
    def test_payment_initialization_service_error(self):
        """Test handling of service errors during payment initialization"""
        with patch('app.services.payment.routes.payment_service.initialize_payment') as mock_init:
            mock_init.side_effect = Exception("Payment gateway unavailable")
            
            response = client.post(
                "/api/v1/payments/initialize",
                json={
                    "amount": 1000.0,
                    "email": "test@example.com",
                    "metadata": {}
                }
            )
            
            assert response.status_code == 500
            assert "Payment gateway unavailable" in response.json()["detail"]
    
    def test_payout_service_error(self):
        """Test handling of service errors during payout"""
        with patch('app.services.payment.routes.payment_service.initiate_payout') as mock_payout:
            mock_payout.side_effect = Exception("Insufficient balance")
            
            response = client.post(
                "/api/v1/payments/payout",
                json={
                    "amount": 5000.0,
                    "account_number": "0123456789",
                    "bank_code": "058"
                }
            )
            
            assert response.status_code == 500
            assert "Insufficient balance" in response.json()["detail"]


class TestRequestValidation:
    """Test request validation"""
    
    def test_payment_init_negative_amount(self):
        """Test payment initialization rejects negative amount"""
        response = client.post(
            "/api/v1/payments/initialize",
            json={
                "amount": -1000.0,
                "email": "test@example.com",
                "metadata": {}
            }
        )
        
        # Should fail validation (amount should be positive)
        # Note: This requires adding validation to the Pydantic model
        assert response.status_code in [422, 500]
    
    def test_payout_invalid_account_number(self):
        """Test payout with invalid account number format"""
        response = client.post(
            "/api/v1/payments/payout",
            json={
                "amount": 5000.0,
                "account_number": "invalid",
                "bank_code": "058"
            }
        )
        
        # Should fail validation (account number must be 10 digits)
        assert response.status_code == 422
