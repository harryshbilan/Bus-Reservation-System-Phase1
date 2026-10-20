<?php

if (!defined('BUSWAY_ADMIN')) {
    http_response_code(403);
    exit('Forbidden');
}

function cleanup_snapshot(PDO $pdo, string $reference, bool $lock = false): array
{
    $suffix = $lock ? ' FOR UPDATE' : '';
    $query = $pdo->prepare(
        'SELECT * FROM reservations WHERE ticket_number = ? ORDER BY reservation_id' . $suffix
    );
    $query->execute([$reference]);
    $reservations = $query->fetchAll(PDO::FETCH_ASSOC);

    if (!$reservations) {
        return ['reservations' => [], 'payments' => [], 'confirmations' => []];
    }
    $ids = array_column($reservations, 'reservation_id');
    $marks = implode(',', array_fill(0, count($ids), '?'));
    $query = $pdo->prepare(
        "SELECT * FROM payments WHERE reservation_id IN ($marks) ORDER BY payment_id" . $suffix
    );
    $query->execute($ids);
    $payments = $query->fetchAll(PDO::FETCH_ASSOC);
    $confirmations = [];

    if ($payments) {
        $paymentIds = array_column($payments, 'payment_id');
        $paymentMarks = implode(',', array_fill(0, count($paymentIds), '?'));
        $query = $pdo->prepare(
            "SELECT * FROM payment_confirmations WHERE payment_id IN ($paymentMarks) ORDER BY payment_id" . $suffix
        );
        $query->execute($paymentIds);
        $confirmations = $query->fetchAll(PDO::FETCH_ASSOC);
    }

    return compact('reservations', 'payments', 'confirmations');
}

function cleanup_fingerprint(array $snapshot): string
{
    return hash('sha256', json_encode($snapshot, JSON_THROW_ON_ERROR));
}

function cleanup_delete(PDO $pdo, string $reference, string $expectedFingerprint): void
{
    $pdo->beginTransaction();

    try {
        $snapshot = cleanup_snapshot($pdo, $reference, true);

        if (!$snapshot['reservations']) {
            throw new RuntimeException('This booking no longer exists.');
        }

        if (!hash_equals($expectedFingerprint, cleanup_fingerprint($snapshot))) {
            throw new RuntimeException('This booking changed after the preview. Review it again before deleting.');
        }
        $reservationIds = array_column($snapshot['reservations'], 'reservation_id');
        $passengerIds = array_values(array_unique(array_filter(array_column($snapshot['reservations'], 'passenger_id'))));
        $paymentIds = array_column($snapshot['payments'], 'payment_id');

        if ($paymentIds) {
            $marks = implode(',', array_fill(0, count($paymentIds), '?'));
            $pdo->prepare("DELETE FROM payment_confirmations WHERE payment_id IN ($marks)")->execute($paymentIds);
            $pdo->prepare("DELETE FROM fare_discounts WHERE payment_id IN ($marks)")->execute($paymentIds);
            $pdo->prepare("DELETE FROM payments WHERE payment_id IN ($marks)")->execute($paymentIds);
        }
        $pdo->prepare('DELETE FROM booking_extras WHERE ticket_number = ?')->execute([$reference]);
        $marks = implode(',', array_fill(0, count($reservationIds), '?'));
        $pdo->prepare("DELETE FROM reservations WHERE reservation_id IN ($marks)")->execute($reservationIds);

        foreach ($passengerIds as $passengerId) {
            $pdo->prepare(
                'DELETE FROM passengers WHERE passenger_id = ?
                 AND NOT EXISTS (SELECT 1 FROM reservations WHERE passenger_id = ?)'
            )->execute([$passengerId, $passengerId]);
        }
        $pdo->commit();
    } catch (Throwable $error) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        throw $error;
    }
}
