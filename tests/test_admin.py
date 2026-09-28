from app.core.config import settings


def test_health_endpoint(client):
    response = client.get("/health")
    assert response.status_code == 200
    data = response.json()
    assert data["status"] == "CONNECTED"
    assert "stats" in data
    assert "latencyMs" in data
    # Ensure sensitive credentials are NOT leaked in health response
    assert "password" not in str(data).lower()
    assert "user" not in data


def test_admin_endpoint_forbidden_without_key(client):
    response = client.get("/admin/db-status")
    assert response.status_code == 403
    assert "Access denied" in response.json()["detail"]


def test_admin_endpoint_authorized(client):
    response = client.get(
        "/admin/db-status",
        headers={"X-Admin-Key": settings.ADMIN_API_KEY}
    )
    assert response.status_code == 200
    data = response.json()
    assert "is_connected" in data
    assert "pool_status" in data
    assert "connection_url_safe" in data
    # Ensure raw password is NOT leaked even in admin status
    assert "***" in data["connection_url_safe"]
