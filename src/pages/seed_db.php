<?php
// seed_database.php
require_once __DIR__ . '/../db/db.php';
require_once __DIR__ . '/../scripts/uuid_create.php';

try {
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->exec('PRAGMA foreign_keys = ON;');

    echo "🚀 Seeding started...\n";

    // --------------------------------------------------------------------
    // COMPANY
    // --------------------------------------------------------------------
    $company_id = uuidv4();
    $pdo->prepare("INSERT INTO Bus_Company (id, name, logo_path) VALUES (?, ?, ?)")
        ->execute([$company_id, 'Yavuzlar Turizm', 'logo.png']);
    echo "✅ Company inserted.\n";

    // --------------------------------------------------------------------
    // USERS
    // --------------------------------------------------------------------
    $password = password_hash('Aa12345!', PASSWORD_ARGON2ID);
    $users = [
        ['Admin User', 'admin@example.com', 'admin', null, null],
        ['Company Admin', 'companyadmin@example.com', 'company', $company_id, null],
        ['John', 'john@example.com', 'user', null, 'male'],
        ['Jane', 'jane@example.com', 'user', null, 'female'],
    ];

    $user_ids = [];
    foreach ($users as $u) {
        $id = uuidv4();
        $pdo->prepare("
            INSERT INTO User (id, full_name, email, role, password, company_id, gender)
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ")->execute([$id, $u[0], $u[1], $u[2], $password, $u[3], $u[4]]);
        $user_ids[$u[1]] = $id;
    }
    echo "✅ Users inserted.\n";

    // --------------------------------------------------------------------
    // COUPONS
    // --------------------------------------------------------------------
    $now = new DateTime();
    $in_30_days = (clone $now)->modify('+30 days')->format('Y-m-d');
    $week_ago = (clone $now)->modify('-7 days')->format('Y-m-d');

    $coupons = [
        // company specific
        ['YAVUZ50', 50, $company_id, 8, $in_30_days],
        // all companies
        ['ALL40', 40, null, 10, $in_30_days],
        // used coupon
        ['USED20', 20, null, 1, $in_30_days],
        // expired
        ['PAST30', 30, null, 9, $week_ago],
    ];

    $coupon_ids = [];
    foreach ($coupons as $c) {
        $cid = uuidv4();
        $pdo->prepare("
            INSERT INTO Coupons (id, code, discount, company_id, usage_limit, expire_date)
            VALUES (?, ?, ?, ?, ?, ?)
        ")->execute([$cid, $c[0], $c[1], $c[2], $c[3], $c[4]]);
        $coupon_ids[$c[0]] = $cid;
    }
    echo "✅ Coupons inserted.\n";

    // --------------------------------------------------------------------
    // USERS USE SOME COUPONS
    // --------------------------------------------------------------------
    // USED20 → used once
    $pdo->prepare("INSERT INTO User_Coupons (id, coupon_id, user_id) VALUES (?, ?, ?)")
        ->execute([uuidv4(), $coupon_ids['USED20'], $user_ids['john@example.com']]);

    // PAST30 → partially used 3–4 times
    $used_users = [
        $user_ids['john@example.com'],
        $user_ids['jane@example.com'],
        $user_ids['companyadmin@example.com']
    ];
    foreach ($used_users as $uid) {
        $pdo->prepare("INSERT INTO User_Coupons (id, coupon_id, user_id) VALUES (?, ?, ?)")
            ->execute([uuidv4(), $coupon_ids['PAST30'], $uid]);
    }
    echo "✅ Coupon usages simulated.\n";

    // --------------------------------------------------------------------
    // TRIPS
    // --------------------------------------------------------------------
    $cities = [
        'New York', 'Los Angeles', 'Chicago', 'Houston', 'Phoenix',
        'Philadelphia', 'San Antonio', 'San Diego', 'Dallas', 'San Jose',
        'Austin', 'Jacksonville', 'Fort Worth', 'Columbus', 'Charlotte',
        'San Francisco', 'Indianapolis', 'Seattle', 'Denver', 'Washington',
        'Boston', 'Nashville', 'Detroit', 'Portland', 'Las Vegas',
        'Miami', 'Atlanta', 'Minneapolis', 'Tampa', 'Orlando'
    ];

    $today = (new DateTime('today'))->format('Y-m-d');
    $tomorrow = (new DateTime('tomorrow'))->format('Y-m-d');

    $trip_ids = [];
    foreach ([$today, $tomorrow] as $date) {
        foreach ($cities as $from) {
            foreach ($cities as $to) {
                if ($from === $to) continue;

                $trip_id = uuidv4();
                $capacity = rand(30, 40);
                $departure_time = "$date " . sprintf("%02d:%02d", rand(5, 22), rand(0, 59));
                $arrival_time = date('Y-m-d H:i', strtotime($departure_time . ' +' . rand(2, 8) . ' hours'));
                $price = rand(100, 400);

                $pdo->prepare("
                    INSERT INTO Trips (id, company_id, bus_type, destination_city, arrival_time, departure_time, departure_city, price, capacity)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
                ")->execute([$trip_id, $company_id, '2+2', $to, $arrival_time, $departure_time, $from, $price, $capacity]);

                // record some IDs to create tickets
                if (rand(0, 100) < 1) { // only for ~1% of trips, to keep DB light
                    $trip_ids[] = $trip_id;
                }
            }
        }
    }
    echo "✅ Trips created (" . count($trip_ids) . " sampled for tickets).\n";

    // --------------------------------------------------------------------
    // TICKETS & SEATS (for sample trips)
    // --------------------------------------------------------------------
    foreach ($trip_ids as $t_id) {
        foreach ([$user_ids['john@example.com'], $user_ids['jane@example.com']] as $uid) {
            $ticket_id = uuidv4();
            $total = rand(100, 400);
            $pdo->prepare("
                INSERT INTO Tickets (id, trip_id, user_id, total_price)
                VALUES (?, ?, ?, ?)
            ")->execute([$ticket_id, $t_id, $uid, $total]);

            $used_seats = rand(1, 3);
            for ($i = 0; $i < $used_seats; $i++) {
                $seat_no = rand(1, 40);
                $pdo->prepare("
                    INSERT INTO Booked_Seats (id, ticket_id, seat_number)
                    VALUES (?, ?, ?)
                ")->execute([uuidv4(), $ticket_id, $seat_no]);
            }
        }
    }
    echo "✅ Tickets & booked seats created.\n";

    echo "🎉 Seeding completed successfully.\n";

} catch (Exception $e) {
    echo "❌ Seeder error: " . $e->getMessage() . "\n";
}
