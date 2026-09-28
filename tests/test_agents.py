def test_create_agent(client):
    response = client.post(
        "/agents",
        json={
            "first_name": "Alice",
            "last_name": "Smith",
            "email": "alice.smith@example.com",
            "phone": "+1 555-1234"
        }
    )
    assert response.status_code == 201
    data = response.json()
    assert data["id"] is not None
    assert data["first_name"] == "Alice"
    assert data["last_name"] == "Smith"
    assert data["email"] == "alice.smith@example.com"
    assert data["phone"] == "+1 555-1234"
    assert "created_at" in data


def test_create_agent_duplicate_email(client):
    payload = {
        "first_name": "Bob",
        "last_name": "Jones",
        "email": "bob.duplicate@example.com",
        "phone": "+1 555-2222"
    }
    res1 = client.post("/agents", json=payload)
    assert res1.status_code == 201

    res2 = client.post("/agents", json=payload)
    assert res2.status_code == 400
    assert "already exists" in res2.json()["detail"]


def test_create_agent_invalid_email(client):
    response = client.post(
        "/agents",
        json={
            "first_name": "Invalid",
            "last_name": "Email",
            "email": "not-an-email",
            "phone": "+1 555-3333"
        }
    )
    assert response.status_code == 422


def test_get_agents(client):
    # Add two agents
    client.post("/agents", json={"first_name": "A1", "last_name": "L1", "email": "a1@test.com"})
    client.post("/agents", json={"first_name": "A2", "last_name": "L2", "email": "a2@test.com"})

    response = client.get("/agents")
    assert response.status_code == 200
    data = response.json()
    assert len(data) >= 2


def test_get_agent_by_id(client):
    create_res = client.post(
        "/agents",
        json={"first_name": "Charlie", "last_name": "Brown", "email": "charlie@example.com"}
    )
    agent_id = create_res.json()["id"]

    response = client.get(f"/agents/{agent_id}")
    assert response.status_code == 200
    assert response.json()["email"] == "charlie@example.com"


def test_get_agent_not_found(client):
    response = client.get("/agents/99999")
    assert response.status_code == 404
    assert "was not found" in response.json()["detail"]


def test_update_agent(client):
    create_res = client.post(
        "/agents",
        json={"first_name": "Diana", "last_name": "Prince", "email": "diana@example.com"}
    )
    agent_id = create_res.json()["id"]

    update_res = client.put(
        f"/agents/{agent_id}",
        json={"first_name": "Diana (Updated)", "phone": "+1 555-9999"}
    )
    assert update_res.status_code == 200
    updated_data = update_res.json()
    assert updated_data["first_name"] == "Diana (Updated)"
    assert updated_data["phone"] == "+1 555-9999"
    assert updated_data["email"] == "diana@example.com"


def test_delete_agent(client):
    create_res = client.post(
        "/agents",
        json={"first_name": "Delete", "last_name": "Me", "email": "delete.me@example.com"}
    )
    agent_id = create_res.json()["id"]

    del_res = client.delete(f"/agents/{agent_id}")
    assert del_res.status_code == 204

    # Verify 404 after deletion
    get_res = client.get(f"/agents/{agent_id}")
    assert get_res.status_code == 404
