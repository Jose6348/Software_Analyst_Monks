-- ============================================================
--  employees_dump.sql
--  Creates the employee table and a self-referencing
--  leader_lead relationship, then seeds 20 employees.
-- ============================================================

-- Drop tables in reverse dependency order
DROP TABLE IF EXISTS leader_lead;
DROP TABLE IF EXISTS employee;

-- ------------------------------------------------------------
--  Table: employee
-- ------------------------------------------------------------
CREATE TABLE employee (
    id            SERIAL          PRIMARY KEY,
    name          VARCHAR(100)    NOT NULL,
    email         VARCHAR(150)    NOT NULL UNIQUE,
    position_name VARCHAR(100)    NOT NULL
);

-- ------------------------------------------------------------
--  Table: leader_lead
--  Models a many-to-many "leader → subordinate" relationship.
--  leader_id : the employee who leads
--  lead_id   : the employee being led
-- ------------------------------------------------------------
CREATE TABLE leader_lead (
    leader_id  INT NOT NULL,
    lead_id    INT NOT NULL,
    PRIMARY KEY (leader_id, lead_id),
    CONSTRAINT fk_leader
        FOREIGN KEY (leader_id) REFERENCES employee(id)
        ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_lead
        FOREIGN KEY (lead_id)   REFERENCES employee(id)
        ON DELETE CASCADE ON UPDATE CASCADE,
    -- An employee cannot lead themselves
    CONSTRAINT chk_no_self_lead CHECK (leader_id <> lead_id)
);

-- ------------------------------------------------------------
--  Seed data – 20 employees
-- ------------------------------------------------------------
INSERT INTO employee (id, name, email, position_name) VALUES
( 1, 'Alice Hartman',    'alice.hartman@company.com',    'CEO'),
( 2, 'Bob Sinclair',     'bob.sinclair@company.com',     'CTO'),
( 3, 'Carol Nguyen',     'carol.nguyen@company.com',     'CFO'),
( 4, 'David Okafor',     'david.okafor@company.com',     'Engineering Manager'),
( 5, 'Eva Müller',       'eva.muller@company.com',       'Engineering Manager'),
( 6, 'Frank Rossi',      'frank.rossi@company.com',      'Product Manager'),
( 7, 'Grace Kim',        'grace.kim@company.com',        'UX Designer'),
( 8, 'Henry Patel',      'henry.patel@company.com',      'Senior Software Engineer'),
( 9, 'Isabelle Dubois',  'isabelle.dubois@company.com',  'Senior Software Engineer'),
(10, 'James Watanabe',   'james.watanabe@company.com',   'Software Engineer'),
(11, 'Karen Oliveira',   'karen.oliveira@company.com',   'Software Engineer'),
(12, 'Liam Johansson',   'liam.johansson@company.com',   'Software Engineer'),
(13, 'Mia Fernandez',    'mia.fernandez@company.com',    'Data Engineer'),
(14, 'Noah Chukwu',      'noah.chukwu@company.com',      'Data Analyst'),
(15, 'Olivia Brooks',    'olivia.brooks@company.com',    'QA Engineer'),
(16, 'Paul Nakamura',    'paul.nakamura@company.com',    'QA Engineer'),
(17, 'Quinn Santos',     'quinn.santos@company.com',     'DevOps Engineer'),
(18, 'Rachel Ivanova',   'rachel.ivanova@company.com',   'Finance Analyst'),
(19, 'Samuel Osei',      'samuel.osei@company.com',      'Finance Analyst'),
(20, 'Tina Bergmann',    'tina.bergmann@company.com',    'HR Specialist');

-- Reset sequence so future INSERTs auto-increment correctly
SELECT setval('employee_id_seq', 20);

-- ------------------------------------------------------------
--  Seed data – leader / lead relationships
--
--  Org hierarchy overview:
--  Alice (CEO)
--    ├─ Bob (CTO)
--    │    ├─ David (Eng Manager)
--    │    │    ├─ Henry  (Sr Engineer)
--    │    │    │    ├─ James  (Engineer)
--    │    │    │    └─ Karen  (Engineer)
--    │    │    └─ Liam   (Engineer)
--    │    ├─ Eva   (Eng Manager)
--    │    │    ├─ Isabelle (Sr Engineer)
--    │    │    ├─ Noah   (Data Analyst)
--    │    │    └─ Mia    (Data Engineer)
--    │    ├─ Grace  (UX Designer)
--    │    ├─ Quinn  (DevOps)
--    │    └─ Paul   (QA)
--    ├─ Carol (CFO)
--    │    ├─ Rachel (Finance Analyst)
--    │    └─ Samuel (Finance Analyst)
--    ├─ Frank (Product Manager)
--    │    └─ Olivia (QA Engineer)
--    └─ Tina  (HR Specialist)
-- ------------------------------------------------------------
INSERT INTO leader_lead (leader_id, lead_id) VALUES
-- Alice leads top-level reports
(1,  2),   -- Alice → Bob
(1,  3),   -- Alice → Carol
(1,  6),   -- Alice → Frank
(1, 20),   -- Alice → Tina

-- Bob leads his direct reports
(2,  4),   -- Bob → David
(2,  5),   -- Bob → Eva
(2,  7),   -- Bob → Grace
(2, 17),   -- Bob → Quinn
(2, 16),   -- Bob → Paul

-- David leads his engineers
(4,  8),   -- David → Henry
(4, 12),   -- David → Liam

-- Henry leads junior engineers
(8, 10),   -- Henry → James
(8, 11),   -- Henry → Karen

-- Eva leads her engineers
(5,  9),   -- Eva → Isabelle
(5, 13),   -- Eva → Mia
(5, 14),   -- Eva → Noah

-- Carol leads finance team
(3, 18),   -- Carol → Rachel
(3, 19),   -- Carol → Samuel

-- Frank leads QA
(6, 15);   -- Frank → Olivia
