def test_create_property_success(client):
    # 1. First create an agent
    agent_res = client.post(
        "/agents",
        json={"first_name": "Marcus", "last_name": "Vance", "email": "marcus.p@test.com"}
    )
    assert agent_res.status_code == 201
    agent_id = agent_res.json()["id"]

    # 2. Create property associated with that agent
    prop_res = client.post(
        "/properties",
        json={
            "title": "Modern Lakefront Villa",
            "description": "Luxurious villa with direct lake access.",
            "property_type": "Villa",
            "price": 1250000.00,
            "address": "45 Lakeview Terrace",
            "city": "Clearwater",
            "bedrooms": 4,
            "bathrooms": 3.5,
            "area": 3200.00,
            "status": "available",
            "agent_id": agent_id
        }
    )
    assert prop_res.status_code == 201
    data = prop_res.json()
    assert data["id"] is not None
    assert data["title"] == "Modern Lakefront Villa"
    assert float(data["price"]) == 1250000.0
    assert data["agent_id"] == agent_id


def test_create_property_invalid_agent(client):
    # Attempt to link to non-existent agent
    response = client.post(
        "/properties",
        json={
            "title": "Orphan Property",
            "property_type": "House",
            "price": 300000.00,
            "address": "123 Nowhere Lane",
            "city": "Ghost Town",
            "bedrooms": 2,
            "bathrooms": 1.0,
            "area": 1000.00,
            "status": "available",
            "agent_id": 99999
        }
    )
    assert response.status_code == 400
    assert "does not exist" in response.json()["detail"]


def test_create_property_negative_price_rejected(client):
    response = client.post(
        "/properties",
        json={
            "title": "Negative Price Property",
            "property_type": "House",
            "price": -5000.00,
            "address": "123 Test St",
            "city": "Testville",
            "area": 1000.00,
            "agent_id": 1
        }
    )
    assert response.status_code == 422


def test_create_property_negative_area_rejected(client):
    response = client.post(
        "/properties",
        json={
            "title": "Negative Area Property",
            "property_type": "House",
            "price": 100000.00,
            "address": "123 Test St",
            "city": "Testville",
            "area": -200.00,
            "agent_id": 1
        }
    )
    assert response.status_code == 422


def test_get_properties_and_filtering(client):
    # Create agent
    agent_res = client.post("/agents", json={"first_name": "A", "last_name": "B", "email": "ab@test.com"})
    agent_id = agent_res.json()["id"]

    # Create properties in different cities and types
    client.post("/properties", json={
        "title": "Downtown Studio",
        "property_type": "Condo",
        "price": 250000.00,
        "address": "100 Main St",
        "city": "Seattle",
        "area": 750.00,
        "agent_id": agent_id
    })
    client.post("/properties", json={
        "title": "Suburban Haven",
        "property_type": "House",
        "price": 550000.00,
        "address": "200 Pine St",
        "city": "Portland",
        "area": 2200.00,
        "agent_id": agent_id
    })

    # Retrieve all
    all_res = client.get("/properties")
    assert all_res.status_code == 200
    assert len(all_res.json()) >= 2

    # Filter by city
    seattle_res = client.get("/properties?city=Seattle")
    assert seattle_res.status_code == 200
    assert len(seattle_res.json()) == 1
    assert seattle_res.json()[0]["city"] == "Seattle"

    # Filter by property_type
    condo_res = client.get("/properties?property_type=Condo")
    assert condo_res.status_code == 200
    assert all(p["property_type"] == "Condo" for p in condo_res.json())


def test_get_property_by_id(client):
    agent_res = client.post("/agents", json={"first_name": "A", "last_name": "B", "email": "ab2@test.com"})
    agent_id = agent_res.json()["id"]

    prop_res = client.post("/properties", json={
        "title": "Cozy Cabin",
        "property_type": "Cabin",
        "price": 320000.00,
        "address": "1 Mountain Pass",
        "city": "Aspen",
        "area": 1400.00,
        "agent_id": agent_id
    })
    prop_id = prop_res.json()["id"]

    get_res = client.get(f"/properties/{prop_id}")
    assert get_res.status_code == 200
    data = get_res.json()
    assert data["title"] == "Cozy Cabin"
    assert data["city"] == "Aspen"


def test_get_property_not_found(client):
    response = client.get("/properties/88888")
    assert response.status_code == 404
    assert "was not found" in response.json()["detail"]


def test_update_property(client):
    agent_res = client.post("/agents", json={"first_name": "A", "last_name": "B", "email": "ab3@test.com"})
    agent_id = agent_res.json()["id"]

    prop_res = client.post("/properties", json={
        "title": "Initial Title",
        "property_type": "House",
        "price": 400000.00,
        "address": "123 Initial Way",
        "city": "Denver",
        "area": 1800.00,
        "agent_id": agent_id
    })
    prop_id = prop_res.json()["id"]

    update_res = client.put(
        f"/properties/{prop_id}",
        json={"title": "Updated Luxury Estate", "price": 450000.00, "status": "pending"}
    )
    assert update_res.status_code == 200
    data = update_res.json()
    assert data["title"] == "Updated Luxury Estate"
    assert float(data["price"]) == 450000.00
    assert data["status"] == "pending"


def test_update_property_with_invalid_agent(client):
    agent_res = client.post("/agents", json={"first_name": "A", "last_name": "B", "email": "ab4@test.com"})
    agent_id = agent_res.json()["id"]

    prop_res = client.post("/properties", json={
        "title": "Test Prop",
        "property_type": "House",
        "price": 200000.00,
        "address": "321 Test St",
        "city": "Miami",
        "area": 1200.00,
        "agent_id": agent_id
    })
    prop_id = prop_res.json()["id"]

    update_res = client.put(f"/properties/{prop_id}", json={"agent_id": 99999})
    assert update_res.status_code == 400
    assert "does not exist" in update_res.json()["detail"]


def test_delete_property(client):
    agent_res = client.post("/agents", json={"first_name": "A", "last_name": "B", "email": "ab5@test.com"})
    agent_id = agent_res.json()["id"]

    prop_res = client.post("/properties", json={
        "title": "To Be Deleted",
        "property_type": "House",
        "price": 100000.00,
        "address": "404 Gone St",
        "city": "Dallas",
        "area": 900.00,
        "agent_id": agent_id
    })
    prop_id = prop_res.json()["id"]

    del_res = client.delete(f"/properties/{prop_id}")
    assert del_res.status_code == 204

    get_res = client.get(f"/properties/{prop_id}")
    assert get_res.status_code == 404
