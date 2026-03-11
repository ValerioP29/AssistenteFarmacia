<?php
require_once(__DIR__ . '/cron_functions.php');

executeCron('cron-quiz-daily.php', function () {
    $pdo = getConnection();

    // Prendi tutte le farmacie attive
    $stmt = $pdo->query("SELECT id FROM jta_pharma WHERE is_active = 1");
    $pharmas = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (empty($pharmas)) return true;

    $errors = 0;
    foreach ($pharmas as $pharma) {
        $result = QuizzesModel::insertFromAI(
            date('Y-m-d'),
            0,   // usa default da get_option
            '',  // categoria random
            (int) $pharma['id']
        );
        if ($result === false) $errors++;
    }

    return $errors === 0;
});