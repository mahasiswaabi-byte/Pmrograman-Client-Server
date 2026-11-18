<?php
header("Content-Type: application/json; charset=utf-8");

if ($_SERVER["REQUEST_METHOD"] !== "GET") {
    http_response_code(405);
    echo json_encode(["success" => false, "message" => "Metode tidak diizinkan."]);
    exit;
}

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

try {
    require_once __DIR__ . "/Koneksi.php";
    mysqli_set_charset($conn, "utf8mb4");

    $announcementsResult = mysqli_query(
        $conn,
        "SELECT `Title` AS title, `Body` AS body, DATE_FORMAT(`PublishedAt`, '%Y-%m-%d %H:%i:%s') AS publishedAt
         FROM `community_announcements`
         ORDER BY `PublishedAt` DESC, `Id` DESC
         LIMIT 6"
    );
    $eventsResult = mysqli_query(
        $conn,
        "SELECT `Title` AS title, `Description` AS description, `Location` AS location,
                DATE_FORMAT(`StartsAt`, '%Y-%m-%d %H:%i:%s') AS startsAt
         FROM `community_events`
         WHERE `StartsAt` >= NOW()
         ORDER BY `StartsAt` ASC
         LIMIT 6"
    );
    $membersResult = mysqli_query(
        $conn,
        "SELECT profiles.`FullName` AS name, profiles.`Regency` AS regency
         FROM `account_profiles` AS profiles
         INNER JOIN `login` ON `login`.`Username` = profiles.`Username`
         WHERE `login`.`Role` = 'user'
           AND profiles.`PublicDirectory` = 1
           AND TRIM(profiles.`FullName`) <> ''
         ORDER BY profiles.`FullName` ASC
         LIMIT 200"
    );

    echo json_encode(
        [
            "success" => true,
            "announcements" => mysqli_fetch_all($announcementsResult, MYSQLI_ASSOC),
            "events" => mysqli_fetch_all($eventsResult, MYSQLI_ASSOC),
            "members" => mysqli_fetch_all($membersResult, MYSQLI_ASSOC),
        ],
        JSON_UNESCAPED_UNICODE
    );
} catch (Throwable $error) {
    error_log("Community data error: " . $error->getMessage());
    http_response_code(500);
    echo json_encode([
        "success" => false,
        "message" => "Data komunitas belum tersedia. Jalankan Koneksi/Setup_Database.sql lalu coba lagi.",
    ], JSON_UNESCAPED_UNICODE);
}
