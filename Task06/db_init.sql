PRAGMA foreign_keys = ON;

BEGIN IMMEDIATE;

DROP VIEW IF EXISTS work_details;
DROP TABLE IF EXISTS performed_works;
DROP TABLE IF EXISTS appointment_services;
DROP TABLE IF EXISTS appointments;
DROP TABLE IF EXISTS work_shifts;
DROP TABLE IF EXISTS master_services;
DROP TABLE IF EXISTS cars;
DROP TABLE IF EXISTS clients;
DROP TABLE IF EXISTS services;
DROP TABLE IF EXISTS masters;

CREATE TABLE masters (
    id INTEGER PRIMARY KEY,
    last_name TEXT NOT NULL CHECK (trim(last_name) <> ''),
    first_name TEXT NOT NULL CHECK (trim(first_name) <> ''),
    middle_name TEXT,
    hired_on TEXT NOT NULL DEFAULT (date('now', 'localtime'))
        CHECK (date(hired_on, '+0 days') IS hired_on),
    dismissed_on TEXT
        CHECK (date(dismissed_on, '+0 days') IS dismissed_on AND dismissed_on >= hired_on),
    salary_percent REAL NOT NULL DEFAULT 30 CHECK (salary_percent BETWEEN 0 AND 100)
) STRICT;

CREATE TABLE services (
    id INTEGER PRIMARY KEY,
    name TEXT NOT NULL UNIQUE CHECK (trim(name) <> ''),
    duration_minutes INTEGER NOT NULL CHECK (duration_minutes BETWEEN 1 AND 1439),
    price_kopecks INTEGER NOT NULL CHECK (price_kopecks >= 0)
) STRICT;

CREATE TABLE master_services (
    master_id INTEGER NOT NULL REFERENCES masters (id) ON DELETE CASCADE,
    service_id INTEGER NOT NULL REFERENCES services (id) ON DELETE RESTRICT,
    PRIMARY KEY (master_id, service_id)
) STRICT;

CREATE TABLE work_shifts (
    master_id INTEGER NOT NULL REFERENCES masters (id) ON DELETE RESTRICT,
    work_date TEXT NOT NULL CHECK (date(work_date, '+0 days') IS work_date),
    start_time TEXT NOT NULL CHECK (strftime('%H:%M', start_time) IS start_time AND start_time < '24:00'),
    end_time TEXT NOT NULL CHECK (strftime('%H:%M', end_time) IS end_time AND end_time < '24:00'),
    PRIMARY KEY (master_id, work_date),
    CHECK (start_time < end_time)
) STRICT;

CREATE TABLE clients (
    id INTEGER PRIMARY KEY,
    full_name TEXT NOT NULL CHECK (trim(full_name) <> ''),
    phone TEXT NOT NULL UNIQUE CHECK (trim(phone) <> '')
) STRICT;

CREATE TABLE cars (
    id INTEGER PRIMARY KEY,
    client_id INTEGER NOT NULL REFERENCES clients (id) ON DELETE RESTRICT,
    plate TEXT NOT NULL UNIQUE CHECK (trim(plate) <> ''),
    make TEXT NOT NULL CHECK (trim(make) <> ''),
    model TEXT NOT NULL CHECK (trim(model) <> '')
) STRICT;

CREATE TABLE appointments (
    id INTEGER PRIMARY KEY,
    car_id INTEGER NOT NULL REFERENCES cars (id) ON DELETE RESTRICT,
    master_id INTEGER NOT NULL,
    work_date TEXT NOT NULL,
    start_time TEXT NOT NULL CHECK (strftime('%H:%M', start_time) IS start_time AND start_time < '24:00'),
    end_time TEXT NOT NULL CHECK (strftime('%H:%M', end_time) IS end_time AND end_time < '24:00'),
    status TEXT NOT NULL DEFAULT 'draft' CHECK (status IN ('draft', 'booked', 'cancelled')),
    FOREIGN KEY (master_id, work_date) REFERENCES work_shifts (master_id, work_date) ON DELETE RESTRICT,
    CHECK (start_time < end_time)
) STRICT;

CREATE TABLE appointment_services (
    appointment_id INTEGER NOT NULL REFERENCES appointments (id) ON DELETE CASCADE,
    service_id INTEGER NOT NULL REFERENCES services (id) ON DELETE RESTRICT,
    planned_minutes INTEGER NOT NULL CHECK (planned_minutes BETWEEN 1 AND 1439),
    PRIMARY KEY (appointment_id, service_id)
) STRICT;

CREATE TABLE performed_works (
    appointment_id INTEGER NOT NULL,
    service_id INTEGER NOT NULL,
    master_id INTEGER NOT NULL REFERENCES masters (id) ON DELETE RESTRICT,
    finished_at TEXT NOT NULL DEFAULT (strftime('%Y-%m-%d %H:%M', 'now', 'localtime'))
        CHECK (strftime('%Y-%m-%d %H:%M', finished_at, '+0 minutes') IS finished_at),
    charged_kopecks INTEGER NOT NULL CHECK (charged_kopecks >= 0),
    commission_percent REAL NOT NULL CHECK (commission_percent BETWEEN 0 AND 100),
    PRIMARY KEY (appointment_id, service_id),
    FOREIGN KEY (appointment_id, service_id)
        REFERENCES appointment_services (appointment_id, service_id) ON DELETE RESTRICT
) STRICT;

CREATE INDEX idx_master_services_service ON master_services (service_id);
CREATE INDEX idx_cars_client ON cars (client_id);
CREATE INDEX idx_appointments_shift ON appointments (master_id, work_date, status, start_time);
CREATE INDEX idx_appointments_car ON appointments (car_id, work_date, status, start_time);
CREATE INDEX idx_appointment_services_service ON appointment_services (service_id);
CREATE INDEX idx_performed_works_master_date ON performed_works (master_id, finished_at);
CREATE INDEX idx_performed_works_date ON performed_works (finished_at);

CREATE TRIGGER appointments_start_draft
BEFORE INSERT ON appointments
WHEN NEW.status <> 'draft'
BEGIN
    SELECT RAISE(ABORT, 'Сначала создайте черновик записи');
END;

CREATE TRIGGER appointments_check_booking
BEFORE UPDATE ON appointments
WHEN NEW.status = 'booked'
BEGIN
    SELECT RAISE(ABORT, 'Запись должна быть в пределах смены работающего мастера')
    WHERE NOT EXISTS (
        SELECT 1
        FROM work_shifts AS shift
        JOIN masters AS master ON master.id = shift.master_id
        WHERE shift.master_id = NEW.master_id
          AND shift.work_date = NEW.work_date
          AND NEW.start_time >= shift.start_time
          AND NEW.end_time <= shift.end_time
          AND NEW.work_date >= master.hired_on
          AND (master.dismissed_on IS NULL OR NEW.work_date <= master.dismissed_on)
    );

    SELECT RAISE(ABORT, 'Время записи должно соответствовать сумме длительностей услуг')
    WHERE (unixepoch(NEW.work_date || ' ' || NEW.end_time) -
           unixepoch(NEW.work_date || ' ' || NEW.start_time)) / 60 <>
          (SELECT COALESCE(SUM(planned_minutes), 0)
           FROM appointment_services WHERE appointment_id = OLD.id);

    SELECT RAISE(ABORT, 'Мастер не оказывает одну из выбранных услуг')
    WHERE EXISTS (
        SELECT 1 FROM appointment_services AS item
        WHERE item.appointment_id = OLD.id
          AND NOT EXISTS (
              SELECT 1 FROM master_services AS skill
              WHERE skill.master_id = NEW.master_id AND skill.service_id = item.service_id
          )
    );

    SELECT RAISE(ABORT, 'Мастер уже занят в это время')
    WHERE EXISTS (
        SELECT 1 FROM appointments AS other
        WHERE other.id <> OLD.id AND other.status = 'booked'
          AND other.master_id = NEW.master_id AND other.work_date = NEW.work_date
          AND other.start_time < NEW.end_time AND other.end_time > NEW.start_time
    );

    SELECT RAISE(ABORT, 'Автомобиль уже записан на это время')
    WHERE EXISTS (
        SELECT 1 FROM appointments AS other
        WHERE other.id <> OLD.id AND other.status = 'booked'
          AND other.car_id = NEW.car_id AND other.work_date = NEW.work_date
          AND other.start_time < NEW.end_time AND other.end_time > NEW.start_time
    );
END;

CREATE TRIGGER appointment_services_insert_draft
BEFORE INSERT ON appointment_services
BEGIN
    SELECT RAISE(ABORT, 'Услуги меняются только в черновике записи')
    WHERE EXISTS (SELECT 1 FROM appointments WHERE id = NEW.appointment_id AND status <> 'draft');

    SELECT RAISE(ABORT, 'Укажите длительность услуги из справочника')
    WHERE NEW.planned_minutes IS NOT (SELECT duration_minutes FROM services WHERE id = NEW.service_id);
END;

CREATE TRIGGER appointment_services_update_draft
BEFORE UPDATE ON appointment_services
BEGIN
    SELECT RAISE(ABORT, 'Услуги меняются только в черновике записи')
    WHERE EXISTS (
        SELECT 1 FROM appointments
        WHERE id IN (OLD.appointment_id, NEW.appointment_id) AND status <> 'draft'
    );

    SELECT RAISE(ABORT, 'Укажите длительность услуги из справочника')
    WHERE (NEW.service_id <> OLD.service_id OR NEW.planned_minutes <> OLD.planned_minutes)
      AND NEW.planned_minutes IS NOT (SELECT duration_minutes FROM services WHERE id = NEW.service_id);
END;

CREATE TRIGGER appointment_services_delete_draft
BEFORE DELETE ON appointment_services
WHEN EXISTS (SELECT 1 FROM appointments WHERE id = OLD.appointment_id AND status <> 'draft')
BEGIN
    SELECT RAISE(ABORT, 'Услуги меняются только в черновике записи');
END;

CREATE TRIGGER work_shifts_keep_bookings
BEFORE UPDATE OF start_time, end_time ON work_shifts
WHEN EXISTS (
    SELECT 1 FROM appointments
    WHERE master_id = OLD.master_id AND work_date = OLD.work_date AND status = 'booked'
      AND (start_time < NEW.start_time OR end_time > NEW.end_time)
)
BEGIN
    SELECT RAISE(ABORT, 'Новая смена должна включать подтверждённые записи');
END;

CREATE TRIGGER masters_keep_work_dates
BEFORE UPDATE OF hired_on, dismissed_on ON masters
WHEN EXISTS (
    SELECT 1 FROM appointments
    WHERE master_id = OLD.id AND status = 'booked'
      AND (work_date < NEW.hired_on OR work_date > NEW.dismissed_on)
    UNION ALL
    SELECT 1 FROM performed_works
    WHERE master_id = OLD.id
      AND (date(finished_at) < NEW.hired_on OR date(finished_at) > NEW.dismissed_on)
)
BEGIN
    SELECT RAISE(ABORT, 'За пределами новых дат найма есть записи или выполненные работы');
END;

CREATE TRIGGER appointments_keep_work_history
BEFORE UPDATE OF car_id, master_id, work_date, start_time, end_time, status ON appointments
WHEN (NEW.status <> 'booked' OR NEW.car_id <> OLD.car_id OR NEW.master_id <> OLD.master_id
      OR NEW.work_date <> OLD.work_date OR NEW.start_time <> OLD.start_time OR NEW.end_time <> OLD.end_time)
 AND EXISTS (SELECT 1 FROM performed_works WHERE appointment_id = OLD.id)
BEGIN
    SELECT RAISE(ABORT, 'У записи уже есть выполненные работы');
END;

CREATE TRIGGER performed_works_check_insert
BEFORE INSERT ON performed_works
BEGIN
    SELECT RAISE(ABORT, 'Работа должна относиться к подтверждённой записи')
    WHERE NOT EXISTS (SELECT 1 FROM appointments WHERE id = NEW.appointment_id AND status = 'booked');

    SELECT RAISE(ABORT, 'Дата работы вне периода найма мастера')
    WHERE NOT EXISTS (
        SELECT 1 FROM masters
        WHERE id = NEW.master_id AND date(NEW.finished_at) >= hired_on
          AND (dismissed_on IS NULL OR date(NEW.finished_at) <= dismissed_on)
    );
END;

CREATE TRIGGER performed_works_check_update
BEFORE UPDATE OF appointment_id, master_id, finished_at ON performed_works
BEGIN
    SELECT RAISE(ABORT, 'Работа должна относиться к подтверждённой записи')
    WHERE NOT EXISTS (SELECT 1 FROM appointments WHERE id = NEW.appointment_id AND status = 'booked');

    SELECT RAISE(ABORT, 'Дата работы вне периода найма мастера')
    WHERE NOT EXISTS (
        SELECT 1 FROM masters
        WHERE id = NEW.master_id AND date(NEW.finished_at) >= hired_on
          AND (dismissed_on IS NULL OR date(NEW.finished_at) <= dismissed_on)
    );
END;

CREATE VIEW work_details AS
SELECT w.master_id, m.last_name, m.first_name, m.middle_name, m.dismissed_on,
       w.appointment_id, w.finished_at, w.service_id, s.name AS service_name,
       w.charged_kopecks, w.commission_percent
FROM performed_works AS w
JOIN masters AS m ON m.id = w.master_id
JOIN services AS s ON s.id = w.service_id;

INSERT INTO masters (id, last_name, first_name, middle_name, hired_on, dismissed_on, salary_percent) VALUES
    (1, 'Громов', 'Артём', 'Сергеевич', '2023-03-14', NULL, 32.5),
    (2, 'Ершова', 'Анна', 'Игоревна', '2024-02-01', NULL, 37),
    (3, 'Литвинов', 'Алексей', 'Олегович', '2024-09-02', NULL, 29),
    (4, 'Орлов', 'Денис', 'Викторович', '2022-06-20', '2026-10-10', 34);

INSERT INTO services (id, name, duration_minutes, price_kopecks) VALUES
    (1, 'Замена масла', 45, 180000),
    (2, 'Диагностика подвески', 60, 250000),
    (3, 'Шиномонтаж', 50, 280000),
    (4, 'Замена тормозных колодок', 90, 460000),
    (5, 'Проверка аккумулятора', 20, 80000),
    (6, 'Замена воздушного фильтра', 20, 110000);

INSERT INTO master_services (master_id, service_id) VALUES
    (1, 1), (1, 2), (1, 4), (1, 6),
    (2, 1), (2, 3), (2, 5), (2, 6),
    (3, 2), (3, 3), (3, 5),
    (4, 1), (4, 2), (4, 4);

CREATE TEMP TABLE demo_dates (tomorrow TEXT, day_after TEXT);
INSERT INTO demo_dates VALUES (date('now', 'localtime', '+1 day'), date('now', 'localtime', '+2 days'));

INSERT INTO work_shifts (master_id, work_date, start_time, end_time) VALUES
    (1, '2026-10-07', '08:30', '17:30'),
    (2, '2026-10-07', '09:00', '18:00'),
    (3, '2026-10-08', '10:00', '19:00'),
    (3, '2026-10-09', '09:00', '17:00'),
    (4, '2026-10-09', '08:00', '15:30'),
    (1, (SELECT tomorrow FROM demo_dates), '08:30', '17:30'),
    (2, (SELECT tomorrow FROM demo_dates), '10:00', '19:00'),
    (3, (SELECT tomorrow FROM demo_dates), '09:00', '17:00'),
    (1, (SELECT day_after FROM demo_dates), '08:30', '17:30'),
    (2, (SELECT day_after FROM demo_dates), '10:00', '19:00'),
    (3, (SELECT day_after FROM demo_dates), '11:00', '18:00');

INSERT INTO clients (id, full_name, phone) VALUES
    (1, 'Олег Свиридов', '+7 000 000-00-01'),
    (2, 'Ирина Корнеева', '+7 000 000-00-02'),
    (3, 'Павел Власов', '+7 000 000-00-03'),
    (4, 'Дарья Нечаева', '+7 000 000-00-04');

INSERT INTO cars (id, client_id, plate, make, model) VALUES
    (1, 1, 'ТЕСТ-01', 'Lada', 'Vesta'),
    (2, 2, 'ТЕСТ-02', 'Kia', 'Rio'),
    (3, 3, 'ТЕСТ-03', 'Renault', 'Duster'),
    (4, 4, 'ТЕСТ-04', 'Skoda', 'Rapid'),
    (5, 1, 'ТЕСТ-05', 'Hyundai', 'Solaris');

INSERT INTO appointments (id, car_id, master_id, work_date, start_time, end_time) VALUES
    (1, 1, 1, '2026-10-07', '09:00', '10:05'),
    (2, 2, 1, '2026-10-07', '10:05', '11:05'),
    (3, 3, 2, '2026-10-07', '09:30', '10:20'),
    (4, 4, 3, '2026-10-08', '11:00', '11:20'),
    (5, 1, 4, '2026-10-09', '08:00', '09:30'),
    (6, 2, 4, '2026-10-09', '09:40', '10:40'),
    (7, 3, 1, (SELECT tomorrow FROM demo_dates), '09:30', '10:15'),
    (8, 4, 2, (SELECT tomorrow FROM demo_dates), '10:00', '10:50'),
    (9, 5, 2, (SELECT tomorrow FROM demo_dates), '10:00', '10:20');

DROP TABLE temp.demo_dates;

WITH chosen(appointment_id, service_id) AS (
    VALUES (1, 1), (1, 6), (2, 2), (3, 3), (4, 5), (5, 4), (6, 2), (7, 1), (8, 3), (9, 5)
)
INSERT INTO appointment_services (appointment_id, service_id, planned_minutes)
SELECT chosen.appointment_id, chosen.service_id, services.duration_minutes
FROM chosen JOIN services ON services.id = chosen.service_id;

UPDATE appointments SET status = 'booked' WHERE id BETWEEN 1 AND 8;
UPDATE appointments SET status = 'cancelled' WHERE id = 8;
UPDATE appointments SET status = 'booked' WHERE id = 9;

WITH completed(appointment_id, service_id, master_id, finished_at, charged_kopecks) AS (
    VALUES (1, 1, 1, '2026-10-07 09:45', 170000),
           (1, 6, 1, '2026-10-07 10:05', 110000),
           (2, 2, 1, '2026-10-07 11:15', 250000),
           (3, 3, 2, '2026-10-07 10:20', 290000),
           (4, 5, 3, '2026-10-08 11:18', 80000),
           (5, 4, 4, '2026-10-09 09:29', 430000),
           (6, 2, 3, '2026-10-09 10:42', 230000)
)
INSERT INTO performed_works (appointment_id, service_id, master_id, finished_at, charged_kopecks, commission_percent)
SELECT c.appointment_id, c.service_id, c.master_id, c.finished_at, c.charged_kopecks, m.salary_percent
FROM completed AS c JOIN masters AS m ON m.id = c.master_id;

COMMIT;
