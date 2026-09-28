CREATE TABLE IF NOT EXISTS agents (
    id BIGSERIAL PRIMARY KEY, first_name VARCHAR(100) NOT NULL,
    last_name VARCHAR(100) NOT NULL, email VARCHAR(255) NOT NULL UNIQUE,
    phone VARCHAR(50), created_at TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS clients (
    id BIGSERIAL PRIMARY KEY, first_name VARCHAR(100) NOT NULL,
    last_name VARCHAR(100) NOT NULL, email VARCHAR(255) NOT NULL UNIQUE,
    phone VARCHAR(50), created_at TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP
);

-- Older mobile builds used `properties` for sync listings. Preserve that table
-- if it has the old shape; mobile data now belongs in `sync_properties`.
DO $$
BEGIN
    IF to_regclass('public.properties') IS NOT NULL
       AND (
           NOT EXISTS (
               SELECT 1 FROM information_schema.columns
               WHERE table_schema = 'public' AND table_name = 'properties'
                 AND column_name = 'id' AND data_type = 'bigint'
           )
           OR EXISTS (
               SELECT required.column_name
               FROM (VALUES ('bedrooms'), ('bathrooms'), ('area'), ('agent_id'), ('updated_at'))
                    AS required(column_name)
               WHERE NOT EXISTS (
                   SELECT 1 FROM information_schema.columns actual
                   WHERE actual.table_schema = 'public'
                     AND actual.table_name = 'properties'
                     AND actual.column_name = required.column_name
               )
           )
       ) THEN
        IF to_regclass('public.legacy_mobile_properties') IS NOT NULL THEN
            RAISE EXCEPTION 'Both properties and legacy_mobile_properties exist; inspect them before migrating';
        END IF;
        ALTER TABLE public.properties RENAME TO legacy_mobile_properties;
    END IF;
END $$;

CREATE TABLE IF NOT EXISTS properties (
    id BIGSERIAL CONSTRAINT properties_crud_pk PRIMARY KEY,
    title VARCHAR(255) NOT NULL, description TEXT,
    property_type VARCHAR(100) NOT NULL, price NUMERIC(12, 2) NOT NULL,
    address VARCHAR(255) NOT NULL, city VARCHAR(100) NOT NULL,
    bedrooms INTEGER NOT NULL DEFAULT 1 CONSTRAINT ck_properties_bedrooms_nonnegative CHECK (bedrooms >= 0),
    bathrooms NUMERIC(3, 1) NOT NULL DEFAULT 1.0 CONSTRAINT ck_properties_bathrooms_nonnegative CHECK (bathrooms >= 0),
    area NUMERIC(10, 2) NOT NULL DEFAULT 1.0 CONSTRAINT ck_properties_area_positive CHECK (area > 0),
    status VARCHAR(50) NOT NULL DEFAULT 'available' CONSTRAINT ck_properties_status_valid CHECK (status IN ('available', 'pending', 'sold', 'rented', 'under_offer')),
    agent_id BIGINT NOT NULL REFERENCES agents(id) ON DELETE CASCADE,
    created_at TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT ck_properties_price_positive CHECK (price > 0)
);

ALTER TABLE properties
    ADD COLUMN IF NOT EXISTS city VARCHAR(100) NOT NULL DEFAULT 'Unknown';

CREATE TABLE IF NOT EXISTS sync_properties (
    id VARCHAR(64) PRIMARY KEY, title VARCHAR(255) NOT NULL, address VARCHAR(255) NOT NULL,
    city_state_zip VARCHAR(128) NOT NULL DEFAULT '', price INTEGER NOT NULL DEFAULT 0 CHECK (price >= 0),
    price_formatted VARCHAR(64) NOT NULL DEFAULT '', is_rental BOOLEAN DEFAULT FALSE,
    beds NUMERIC(4, 1) NOT NULL DEFAULT 3 CHECK (beds >= 0),
    baths NUMERIC(4, 1) NOT NULL DEFAULT 2 CHECK (baths >= 0),
    sqft INTEGER NOT NULL DEFAULT 1500 CHECK (sqft > 0), property_type VARCHAR(64) NOT NULL DEFAULT 'House',
    description TEXT NOT NULL DEFAULT '', image_res_id INTEGER DEFAULT 0,
    media_uris TEXT DEFAULT '', is_favorite BOOLEAN DEFAULT FALSE,
    status VARCHAR(64) DEFAULT 'Active', available_dates TEXT DEFAULT '',
    available_time_slots TEXT DEFAULT '', amenities TEXT DEFAULT '', year_built INTEGER DEFAULT 2023,
    agent_name VARCHAR(128) DEFAULT 'Sarah Jenkins', agent_title VARCHAR(255) DEFAULT '',
    agent_phone VARCHAR(64) DEFAULT '', agent_email VARCHAR(128) DEFAULT '',
    created_at TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS viewing_appointments (
    id VARCHAR(64) PRIMARY KEY, property_id VARCHAR(64) REFERENCES sync_properties(id) ON DELETE CASCADE,
    property_title VARCHAR(255) NOT NULL, property_address VARCHAR(255) NOT NULL,
    client_name VARCHAR(128) NOT NULL, client_phone VARCHAR(64) DEFAULT '',
    client_email VARCHAR(128) DEFAULT '', appointment_date VARCHAR(64) NOT NULL,
    time_slot VARCHAR(64) NOT NULL, appointment_type VARCHAR(64) DEFAULT 'Viewing',
    status VARCHAR(32) DEFAULT 'UPCOMING', notes TEXT DEFAULT '', timestamp_epoch BIGINT NOT NULL,
    created_at TIMESTAMPTZ DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS inquiries (
    id VARCHAR(64) PRIMARY KEY, property_id VARCHAR(64) REFERENCES sync_properties(id) ON DELETE CASCADE,
    property_title VARCHAR(255) NOT NULL, property_address VARCHAR(255) NOT NULL,
    sender_name VARCHAR(128) NOT NULL, sender_email VARCHAR(128) NOT NULL,
    sender_phone VARCHAR(64) NOT NULL, message TEXT NOT NULL, time_ago VARCHAR(64) DEFAULT 'Just now',
    is_unread BOOLEAN DEFAULT TRUE, updated_at_epoch BIGINT NOT NULL,
    created_at TIMESTAMPTZ DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS chat_messages (
    id VARCHAR(64) PRIMARY KEY, inquiry_id VARCHAR(64) NOT NULL REFERENCES inquiries(id) ON DELETE CASCADE,
    sender VARCHAR(32) NOT NULL, message_text TEXT NOT NULL, time_sent VARCHAR(64) NOT NULL,
    is_from_me BOOLEAN DEFAULT TRUE, created_at TIMESTAMPTZ DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS notifications (
    id VARCHAR(64) PRIMARY KEY, title VARCHAR(255) NOT NULL, message TEXT NOT NULL,
    timestamp_formatted VARCHAR(64) NOT NULL, notification_type VARCHAR(64) NOT NULL,
    is_read BOOLEAN DEFAULT FALSE, target_id VARCHAR(64), created_at TIMESTAMPTZ DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX IF NOT EXISTS ix_properties_city_status ON properties(city, status);
CREATE INDEX IF NOT EXISTS ix_viewing_appointments_property_id ON viewing_appointments(property_id);
CREATE INDEX IF NOT EXISTS ix_inquiries_property_id ON inquiries(property_id);
CREATE INDEX IF NOT EXISTS ix_chat_messages_inquiry_id ON chat_messages(inquiry_id);
CREATE INDEX IF NOT EXISTS ix_notifications_is_read ON notifications(is_read);
