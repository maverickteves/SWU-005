def test_sync_push_and_pull(client):
    payload = {
        "properties": [{
            "id": "sync-prop-1",
            "title": "Safe Listing",
            "address": "1 Main Street",
            "cityStateZip": "Cebu City",
            "price": 100000,
            "sqft": 1000,
        }],
        "appointments": [{
            "id": "appt-1",
            "propertyId": "sync-prop-1",
            "propertyTitle": "Safe Listing",
            "propertyAddress": "1 Main Street",
            "clientName": "Test Client",
            "appointment_date": "2026-10-01",
            "timeSlot": "10:00 AM",
        }],
        "inquiries": [],
        "notifications": [],
    }

    response = client.post("/api/sync/push", json=payload)
    assert response.status_code == 200
    assert response.json()["success"] is True

    snapshot = client.get("/api/sync/pull")
    assert snapshot.status_code == 200
    assert snapshot.json()["properties"][0]["id"] == "sync-prop-1"
    assert snapshot.json()["appointments"][0]["id"] == "appt-1"


def test_sync_push_rolls_back_the_entire_batch(client):
    response = client.post("/api/sync/push", json={
        "properties": [{
            "id": "should-rollback",
            "title": "Temporary",
            "address": "1 Main Street",
            "price": 100,
            "sqft": 100,
        }],
        "notifications": [{"id": "notification-1", "title": "Missing message"}],
    })
    assert response.status_code == 422

    snapshot = client.get("/api/sync/pull")
    assert snapshot.status_code == 200
    assert snapshot.json()["properties"] == []
