def test_create_client(client):
    response = client.post(
        "/clients",
        json={
            "first_name": "Emma",
            "last_name": "Watson",
            "email": "emma.watson@example.com",
            "phone": "+1 555-4321"
        }
    )
    assert response.status_code == 201
    data = response.json()
    assert data["id"] is not None
    assert data["first_name"] == "Emma"
    assert data["last_name"] == "Watson"
    assert data["email"] == "emma.watson@example.com"


def test_create_client_duplicate_email(client):
    payload = {
        "first_name": "John",
        "last_name": "Doe",
        "email": "john.duplicate@example.com"
    }
    client.post("/clients", json=payload)
    res2 = client.post("/clients", json=payload)
    assert res2.status_code == 400
    assert "already exists" in res2.json()["detail"]


def test_create_client_invalid_email(client):
    response = client.post(
        "/clients",
        json={
            "first_name": "Bad",
            "last_name": "Email",
            "email": "not-valid"
        }
    )
    assert response.status_code == 422


def test_get_clients(client):
    client.post("/clients", json={"first_name": "C1", "last_name": "L1", "email": "c1@example.com"})
    client.post("/clients", json={"first_name": "C2", "last_name": "L2", "email": "c2@example.com"})

    response = client.get("/clients")
    assert response.status_code == 200
    data = response.json()
    assert len(data) >= 2


def test_get_client_by_id(client):
    create_res = client.post(
        "/clients",
        json={"first_name": "George", "last_name": "Clark", "email": "george@example.com"}
    )
    client_id = create_res.json()["id"]

    response = client.get(f"/clients/{client_id}")
    assert response.status_code == 200
    assert response.json()["first_name"] == "George"


def test_get_client_not_found(client):
    response = client.get("/clients/99999")
    assert response.status_code == 404
    assert "was not found" in response.json()["detail"]


def test_update_client(client):
    create_res = client.post(
        "/clients",
        json={"first_name": "Hannah", "last_name": "Abbott", "email": "hannah@example.com"}
    )
    client_id = create_res.json()["id"]

    update_res = client.put(
        f"/clients/{client_id}",
        json={"last_name": "Longbottom", "phone": "+1 555-8888"}
    )
    assert update_res.status_code == 200
    data = update_res.json()
    assert data["last_name"] == "Longbottom"
    assert data["phone"] == "+1 555-8888"


def test_delete_client(client):
    create_res = client.post(
        "/clients",
        json={"first_name": "Temp", "last_name": "User", "email": "temp@example.com"}
    )
    client_id = create_res.json()["id"]

    del_res = client.delete(f"/clients/{client_id}")
    assert del_res.status_code == 204

    get_res = client.get(f"/clients/{client_id}")
    assert get_res.status_code == 404
