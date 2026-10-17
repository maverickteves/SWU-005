CREATE EXTENSION IF NOT EXISTS "uuid-ossp";

DROP TABLE IF EXISTS users CASCADE;
CREATE TABLE users (
    id VARCHAR(100) PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    email VARCHAR(255) UNIQUE NOT NULL,
    phone VARCHAR(50) DEFAULT '',
    role VARCHAR(20) DEFAULT 'BUYER',
    license_no VARCHAR(100) DEFAULT '',
    agency VARCHAR(255) DEFAULT '',
    professional_role VARCHAR(100) DEFAULT 'Licensed Agent',
    password_hash VARCHAR(255) DEFAULT '',
    created_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX idx_users_email ON users(email);
CREATE INDEX idx_users_role ON users(role);

DROP TABLE IF EXISTS agent_profiles CASCADE;
CREATE TABLE agent_profiles (
    id VARCHAR(100) PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    title VARCHAR(255) NOT NULL,
    license_no VARCHAR(100) NOT NULL,
    phone VARCHAR(50) NOT NULL,
    email VARCHAR(255) NOT NULL,
    rating NUMERIC(3,2) DEFAULT 4.90,
    reviews_count INT DEFAULT 124,
    total_sales_volume VARCHAR(50) DEFAULT '₱48.2M',
    active_listings_count INT DEFAULT 0,
    bio TEXT,
    updated_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP
);

DROP TABLE IF EXISTS properties CASCADE;
CREATE TABLE properties (
    id VARCHAR(100) PRIMARY KEY,
    title VARCHAR(255) NOT NULL,
    address VARCHAR(255) NOT NULL,
    city_state_zip VARCHAR(255) NOT NULL,
    price BIGINT NOT NULL,
    price_formatted VARCHAR(100) NOT NULL,
    is_rental BOOLEAN DEFAULT FALSE,
    beds NUMERIC(3,1) NOT NULL DEFAULT 1.0,
    baths NUMERIC(3,1) NOT NULL DEFAULT 1.0,
    sqft INT NOT NULL DEFAULT 500,
    property_type VARCHAR(100) NOT NULL DEFAULT 'Condominium',
    description TEXT,
    image_res_id INT DEFAULT 0,
    media_uris TEXT DEFAULT '[]',
    is_favorite BOOLEAN DEFAULT FALSE,
    status VARCHAR(50) DEFAULT 'Active',
    available_dates TEXT DEFAULT '["Mon, Oct 14", "Tue, Oct 15", "Wed, Oct 16", "Thu, Oct 17"]',
    available_time_slots TEXT DEFAULT '["10:00 AM", "1:30 PM", "4:00 PM", "5:30 PM"]',
    amenities TEXT DEFAULT '["Modern Design", "High Speed Internet", "Near Transit"]',
    year_built INT DEFAULT 2022,
    agent_name VARCHAR(255) DEFAULT 'Sarah Jenkins',
    agent_title VARCHAR(255) DEFAULT 'Premier Real Estate Broker • Cebu City Properties',
    agent_phone VARCHAR(50) DEFAULT '+63 917 555 0192',
    agent_email VARCHAR(255) DEFAULT 'sarah.jenkins@estateflow.com',
    created_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX idx_properties_type ON properties(property_type);
CREATE INDEX idx_properties_price ON properties(price);
CREATE INDEX idx_properties_is_rental ON properties(is_rental);

DROP TABLE IF EXISTS viewing_appointments CASCADE;
CREATE TABLE viewing_appointments (
    id VARCHAR(100) PRIMARY KEY,
    property_id VARCHAR(100) REFERENCES properties(id) ON DELETE SET NULL,
    property_title VARCHAR(255) NOT NULL,
    property_address VARCHAR(255) NOT NULL,
    client_name VARCHAR(255) NOT NULL,
    client_phone VARCHAR(50) DEFAULT '',
    client_email VARCHAR(255) DEFAULT '',
    date VARCHAR(100) NOT NULL,
    time_slot VARCHAR(50) NOT NULL,
    type VARCHAR(50) DEFAULT 'Viewing',
    status VARCHAR(50) DEFAULT 'UPCOMING',
    notes TEXT,
    created_timestamp BIGINT NOT NULL DEFAULT (EXTRACT(EPOCH FROM CURRENT_TIMESTAMP) * 1000)::BIGINT,
    created_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX idx_appointments_client_email ON viewing_appointments(client_email);
CREATE INDEX idx_appointments_status ON viewing_appointments(status);

DROP TABLE IF EXISTS inquiries CASCADE;
CREATE TABLE inquiries (
    id VARCHAR(100) PRIMARY KEY,
    property_id VARCHAR(100) REFERENCES properties(id) ON DELETE SET NULL,
    property_title VARCHAR(255) NOT NULL,
    property_address VARCHAR(255) DEFAULT '',
    sender_name VARCHAR(255) NOT NULL,
    sender_email VARCHAR(255) NOT NULL,
    sender_phone VARCHAR(50) DEFAULT '',
    message TEXT NOT NULL,
    time_ago VARCHAR(50) DEFAULT 'Just now',
    is_unread BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP
);

DROP TABLE IF EXISTS inquiry_messages CASCADE;
CREATE TABLE inquiry_messages (
    id VARCHAR(100) PRIMARY KEY,
    inquiry_id VARCHAR(100) NOT NULL REFERENCES inquiries(id) ON DELETE CASCADE,
    sender VARCHAR(50) NOT NULL,
    text TEXT NOT NULL,
    time VARCHAR(50) DEFAULT 'Just now',
    is_from_me BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX idx_inquiry_messages_inquiry_id ON inquiry_messages(inquiry_id);

DROP TABLE IF EXISTS notifications CASCADE;
CREATE TABLE notifications (
    id VARCHAR(100) PRIMARY KEY,
    title VARCHAR(255) NOT NULL,
    message TEXT NOT NULL,
    timestamp_formatted VARCHAR(50) DEFAULT 'Just now',
    type VARCHAR(50) NOT NULL DEFAULT 'VIEWING_BOOKED',
    is_read BOOLEAN DEFAULT FALSE,
    target_id VARCHAR(100),
    created_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX idx_notifications_is_read ON notifications(is_read);

INSERT INTO agent_profiles (id, name, title, license_no, phone, email, rating, reviews_count, total_sales_volume, active_listings_count, bio)
VALUES (
    'agent_verified_sarah',
    'Sarah Jenkins',
    'Premier Real Estate Broker • Cebu City Properties',
    'PRC-REB-0094821',
    '+63 917 555 0192',
    'sarah.jenkins@estateflow.com',
    4.90,
    124,
    '₱48.2M',
    5,
    'Specializing in premier residential condominiums, townhomes, and luxury family estates across Cebu City, Cebu IT Park, and Cebu Business Park.'
) ON CONFLICT (id) DO NOTHING;

INSERT INTO users (id, name, email, phone, role, license_no, agency, professional_role, password_hash)
VALUES (
    'agent_verified_sarah',
    'Sarah Jenkins',
    'sarah.jenkins@estateflow.com',
    '+63 917 555 0192',
    'AGENT',
    'PRC-REB-0094821',
    'Estateflow Luxury Realty',
    'Premier Real Estate Broker',
    '$2y$10$TcyIP70/GCakorNWPAfg6uG2Us5AWCE0mEPIwUnLA8zCz5.Qh0haq'
) ON CONFLICT (id) DO NOTHING;

INSERT INTO users (id, name, email, phone, role, password_hash)
VALUES (
    'client_alex_turner',
    'Alex Turner',
    'alex.turner@example.com',
    '+1 (555) 019-8234',
    'BUYER',
    '$2y$10$TcyIP70/GCakorNWPAfg6uG2Us5AWCE0mEPIwUnLA8zCz5.Qh0haq'
) ON CONFLICT (id) DO NOTHING;

INSERT INTO properties (
    id, title, address, city_state_zip, price, price_formatted, is_rental, beds, baths, sqft,
    property_type, description, media_uris, is_favorite, status, available_dates, available_time_slots,
    amenities, year_built, agent_name, agent_title, agent_phone, agent_email
) VALUES
(
    'prop_pinecrest',
    'The Pinecrest Glasshouse Sanctuary',
    '88 High Ridge Way, Beverly Crest',
    'Los Angeles, CA 90210',
    3450000,
    '₱3,450,000',
    FALSE,
    4.0,
    4.5,
    3850,
    'Villa / House',
    'Perched above the city with commanding valley views, this architectural masterwork features floor-to-ceiling automated glass pocket walls, radiant Italian terrazzo flooring, zero-edge heated infinity pool, and designer kitchen with Miele appliances.',
    '["android.resource://com.example/drawable/property_pinecrest_1787131698724"]',
    TRUE,
    'Active',
    '["Mon, Oct 14", "Tue, Oct 15", "Wed, Oct 16", "Thu, Oct 17"]',
    '["10:00 AM", "1:30 PM", "4:00 PM", "5:30 PM"]',
    '["Infinity Pool", "Panoramic Valley Views", "Wine Cellar", "Smart Home Automation", "Private Solar Microgrid", "Sub-Zero Chef Suite"]',
    2023,
    'Sarah Jenkins',
    'Premier Real Estate Broker • Cebu City Properties',
    '+63 917 555 0192',
    'sarah.jenkins@estateflow.com'
),
(
    'prop_skyline',
    'Skyline Penthouse at The Zenith',
    '450 Marina Boulevard, Penthouse 42B',
    'San Francisco, CA 94123',
    2150000,
    '₱2,150,000',
    FALSE,
    3.0,
    3.0,
    2400,
    'Penthouse',
    'Double-height ceiling penthouse perched atop the 42nd level, showcasing wrap-around panoramic perspectives of the bay, bridges, and city skyline. Includes private key-accessed high-speed elevator and 24/7 dedicated concierge.',
    '["android.resource://com.example/drawable/property_skyline_1787131724474"]',
    FALSE,
    'Active',
    '["Mon, Oct 14", "Wed, Oct 16", "Fri, Oct 18", "Sat, Oct 19"]',
    '["11:00 AM", "2:00 PM", "4:30 PM"]',
    '["Private Sky Terrace", "Concierge 24/7", "Valet Parking", "Dual Spa Ensuite", "EV Charging Station"]',
    2022,
    'Sarah Jenkins',
    'Premier Real Estate Broker • Cebu City Properties',
    '+63 917 555 0192',
    'sarah.jenkins@estateflow.com'
),
(
    'prop_urban_loft',
    'The Industrial Foundry Modern Loft',
    '724 Arts District Promenade',
    'Denver, CO 80205',
    850000,
    '₱850,000',
    FALSE,
    2.0,
    2.0,
    1650,
    'Loft',
    'Historic brick-and-timber conversion loft with 18-foot soaring timber beams, custom black steel oversized windows, polished epoxy concrete flooring, and an open entertainer kitchen with quartz waterfall island.',
    '["android.resource://com.example/drawable/property_urban_loft_1787131739575"]',
    TRUE,
    'Active',
    '["Tue, Oct 15", "Wed, Oct 16", "Thu, Oct 17"]',
    '["10:00 AM", "1:00 PM", "3:30 PM", "5:00 PM"]',
    '["Exposed Brick", "18-ft Ceilings", "Rooftop Lounge Access", "Pet Spa", "Direct Freight Elevator"]',
    2020,
    'Sarah Jenkins',
    'Premier Real Estate Broker • Cebu City Properties',
    '+63 917 555 0192',
    'sarah.jenkins@estateflow.com'
),
(
    'prop_sunny_meadows',
    'Sunny Meadows Craftsman Estate',
    '1240 Whispering Pines Lane',
    'Austin, TX 78704',
    1280000,
    '₱1,280,000',
    FALSE,
    4.0,
    3.5,
    3100,
    'Single Family',
    'Warm, welcoming modern Craftsman nestled on a lush quarter-acre lot with majestic live oaks. Features wrap-around mahogany porch, white oak wide-plank flooring, primary spa suite, and a screened sunroom overlooking the manicured grounds.',
    '["android.resource://com.example/drawable/property_sunny_meadows_1787131753558"]',
    FALSE,
    'Active',
    '["Mon, Oct 14", "Thu, Oct 17", "Sat, Oct 19"]',
    '["9:30 AM", "11:30 AM", "2:00 PM", "4:00 PM"]',
    '["Sprawling Garden", "Covered Patio & Outdoor BBQ", "Home Office Suite", "EV Ready 2-Car Garage"]',
    2021,
    'Sarah Jenkins',
    'Premier Real Estate Broker • Cebu City Properties',
    '+63 917 555 0192',
    'sarah.jenkins@estateflow.com'
),
(
    'prop_cedar_townhouse',
    'Cedar Heights Luxury Brownstone',
    '518 Montgomery St, Unit 3',
    'Jersey City, NJ 07302',
    6500,
    '₱6,500 / mo',
    TRUE,
    3.0,
    2.5,
    1950,
    'Townhouse',
    'Spectacular modern brownstone residence available for executive lease. Tri-level living with private landscaped bluestone courtyard garden, bespoke millwork, gas fireplace, and seamless 12-minute transit into Lower Manhattan.',
    '["android.resource://com.example/drawable/property_townhouse_1787131686098"]',
    FALSE,
    'Active',
    '["Tue, Oct 15", "Wed, Oct 16", "Fri, Oct 18"]',
    '["10:00 AM", "12:00 PM", "3:00 PM", "5:30 PM"]',
    '["Private Garden Courtyard", "Modern Chef Kitchen", "Gas Fireplace", "Smart Security", "Walk to PATH Station"]',
    2022,
    'Sarah Jenkins',
    'Premier Real Estate Broker • Cebu City Properties',
    '+63 917 555 0192',
    'sarah.jenkins@estateflow.com'
) ON CONFLICT (id) DO NOTHING;

INSERT INTO viewing_appointments (
    id, property_id, property_title, property_address, client_name, client_phone, client_email,
    date, time_slot, type, status, notes
) VALUES
(
    'appt_demo_1',
    'prop_pinecrest',
    'The Pinecrest Glasshouse Sanctuary',
    '88 High Ridge Way, Beverly Crest',
    'David Miller',
    '+1 (555) 234-5678',
    'david.miller@example.com',
    'Mon, Oct 14',
    '1:30 PM',
    'Viewing',
    'UPCOMING',
    'Pre-approved luxury buyer requesting private walk-through of the master wing and wine cellar.'
),
(
    'appt_demo_2',
    'prop_skyline',
    'Skyline Penthouse at The Zenith',
    '450 Marina Boulevard, Penthouse 42B',
    'Sophia Chen',
    '+1 (555) 876-5432',
    'sophia.chen@example.com',
    'Wed, Oct 16',
    '4:30 PM',
    'Viewing',
    'UPCOMING',
    'Client wants sunset viewing to assess evening bay lighting.'
) ON CONFLICT (id) DO NOTHING;

INSERT INTO inquiries (
    id, property_id, property_title, property_address, sender_name, sender_email, sender_phone,
    message, time_ago, is_unread
) VALUES
(
    'inq_demo_1',
    'prop_pinecrest',
    'The Pinecrest Glasshouse Sanctuary',
    '88 High Ridge Way, Beverly Crest',
    'Marcus Vance',
    'marcus.vance@example.com',
    '+1 (555) 987-1234',
    'Hello Sarah, what are the HOA dues and does the property have backup solar batteries installed?',
    '2 hours ago',
    TRUE
) ON CONFLICT (id) DO NOTHING;

INSERT INTO inquiry_messages (id, inquiry_id, sender, text, time, is_from_me)
VALUES
(
    'msg_demo_1_1',
    'inq_demo_1',
    'user',
    'Hello Sarah, what are the HOA dues and does the property have backup solar batteries installed?',
    '2 hours ago',
    FALSE
),
(
    'msg_demo_1_2',
    'inq_demo_1',
    'agent',
    'Hi Marcus! There are no HOA fees on this parcel. It has 3 Tesla Powerwall 3 batteries included.',
    '1 hour ago',
    TRUE
) ON CONFLICT (id) DO NOTHING;

INSERT INTO notifications (id, title, message, timestamp_formatted, type, is_read, target_id)
VALUES
(
    'notif_demo_1',
    'Viewing Appointment Booked',
    'David Miller scheduled a viewing for The Pinecrest Glasshouse Sanctuary on Mon, Oct 14 at 1:30 PM.',
    '10 mins ago',
    'VIEWING_BOOKED',
    FALSE,
    'appt_demo_1'
),
(
    'notif_demo_2',
    'New Lead Inquiry',
    'Marcus Vance submitted an inquiry on The Pinecrest Glasshouse Sanctuary.',
    '2 hours ago',
    'NEW_INQUIRY',
    FALSE,
    'inq_demo_1'
) ON CONFLICT (id) DO NOTHING;

DROP TABLE IF EXISTS login_attempts CASCADE;
CREATE TABLE login_attempts (
    id BIGSERIAL PRIMARY KEY,
    attempt_key VARCHAR(300) NOT NULL,
    attempted_at TIMESTAMP WITH TIME ZONE NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX idx_login_attempts_key_time ON login_attempts(attempt_key, attempted_at);
