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

CREATE TABLE IF NOT EXISTS public.properties (
    id BIGSERIAL CONSTRAINT properties_crud_pk PRIMARY KEY,
    title VARCHAR(255) NOT NULL,
    description TEXT,
    property_type VARCHAR(100) NOT NULL,
    price NUMERIC(12, 2) NOT NULL CONSTRAINT ck_properties_price_positive CHECK (price > 0),
    address VARCHAR(255) NOT NULL,
    city VARCHAR(100) NOT NULL DEFAULT 'Unknown',
    bedrooms INTEGER NOT NULL DEFAULT 1 CONSTRAINT ck_properties_bedrooms_nonnegative CHECK (bedrooms >= 0),
    bathrooms NUMERIC(3, 1) NOT NULL DEFAULT 1.0 CONSTRAINT ck_properties_bathrooms_nonnegative CHECK (bathrooms >= 0),
    area NUMERIC(10, 2) NOT NULL DEFAULT 1.0 CONSTRAINT ck_properties_area_positive CHECK (area > 0),
    status VARCHAR(50) NOT NULL DEFAULT 'available' CONSTRAINT ck_properties_status_valid CHECK (status IN ('available', 'pending', 'sold', 'rented', 'under_offer')),
    agent_id BIGINT NOT NULL REFERENCES public.agents(id) ON DELETE CASCADE,
    created_at TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX IF NOT EXISTS ix_properties_city_status
    ON public.properties(city, status);
