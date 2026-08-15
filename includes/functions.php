<?php
/**
 * Easy-Loan-Manager — shared helper functions.
 */

// --- Translation -----------------------------------------------------------
function t(string $key): string
{
    global $LANG;
    return $LANG[$key] ?? $key;
}

// --- CSRF --------------------------------------------------------------------
function csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars(csrf_token()) . '">';
}

function validate_csrf(): bool
{
    $token = $_POST['csrf_token'] ?? '';
    return is_string($token) && hash_equals($_SESSION['csrf_token'] ?? '', $token);
}

function require_csrf(): void
{
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && !validate_csrf()) {
        http_response_code(400);
        die('Invalid or expired form submission (CSRF check failed). Please go back and try again.');
    }
}

// --- Sanitization / formatting ---------------------------------------------
function sanitize(?string $data): string
{
    return htmlspecialchars(trim($data ?? ''), ENT_QUOTES, 'UTF-8');
}

function format_currency(float $amount): string
{
    static $symbol = null;
    if ($symbol === null) {
        $row = fetch_one('SELECT currency_symbol FROM settings WHERE id = 1');
        $symbol = $row['currency_symbol'] ?? '৳';
    }
    return $symbol . ' ' . number_format($amount, 2);
}

function format_date(?string $date, string $format = 'd M Y'): string
{
    if (!$date) return '';
    $ts = strtotime($date);
    return $ts ? date($format, $ts) : '';
}

function format_datetime(?string $datetime): string
{
    return format_date($datetime, 'd M Y, h:i A');
}

// --- Number to words (South Asian lakh/crore grouping, currency = Taka) ----
function bn_num_word(int $n): string
{
    static $words = [
        0 => 'শূন্য', 1 => 'এক', 2 => 'দুই', 3 => 'তিন', 4 => 'চার', 5 => 'পাঁচ', 6 => 'ছয়', 7 => 'সাত', 8 => 'আট', 9 => 'নয়',
        10 => 'দশ', 11 => 'এগারো', 12 => 'বারো', 13 => 'তেরো', 14 => 'চৌদ্দ', 15 => 'পনেরো', 16 => 'ষোলো', 17 => 'সতেরো', 18 => 'আঠারো', 19 => 'উনিশ',
        20 => 'বিশ', 21 => 'একুশ', 22 => 'বাইশ', 23 => 'তেইশ', 24 => 'চব্বিশ', 25 => 'পঁচিশ', 26 => 'ছাব্বিশ', 27 => 'সাতাশ', 28 => 'আটাশ', 29 => 'ঊনত্রিশ',
        30 => 'ত্রিশ', 31 => 'একত্রিশ', 32 => 'বত্রিশ', 33 => 'তেত্রিশ', 34 => 'চৌত্রিশ', 35 => 'পঁয়ত্রিশ', 36 => 'ছত্রিশ', 37 => 'সাঁইত্রিশ', 38 => 'আটত্রিশ', 39 => 'ঊনচল্লিশ',
        40 => 'চল্লিশ', 41 => 'একচল্লিশ', 42 => 'বিয়াল্লিশ', 43 => 'তেতাল্লিশ', 44 => 'চুয়াল্লিশ', 45 => 'পঁয়তাল্লিশ', 46 => 'ছেচল্লিশ', 47 => 'সাতচল্লিশ', 48 => 'আটচল্লিশ', 49 => 'ঊনপঞ্চাশ',
        50 => 'পঞ্চাশ', 51 => 'একান্ন', 52 => 'বায়ান্ন', 53 => 'তিপ্পান্ন', 54 => 'চুয়ান্ন', 55 => 'পঞ্চান্ন', 56 => 'ছাপ্পান্ন', 57 => 'সাতান্ন', 58 => 'আটান্ন', 59 => 'ঊনষাট',
        60 => 'ষাট', 61 => 'একষট্টি', 62 => 'বাষট্টি', 63 => 'তেষট্টি', 64 => 'চৌষট্টি', 65 => 'পঁয়ষট্টি', 66 => 'ছেষট্টি', 67 => 'সাতষট্টি', 68 => 'আটষট্টি', 69 => 'ঊনসত্তর',
        70 => 'সত্তর', 71 => 'একাত্তর', 72 => 'বাহাত্তর', 73 => 'তিয়াত্তর', 74 => 'চুয়াত্তর', 75 => 'পঁচাত্তর', 76 => 'ছিয়াত্তর', 77 => 'সাতাত্তর', 78 => 'আটাত্তর', 79 => 'ঊনআশি',
        80 => 'আশি', 81 => 'একাশি', 82 => 'বিরাশি', 83 => 'তিরাশি', 84 => 'চুরাশি', 85 => 'পঁচাশি', 86 => 'ছিয়াশি', 87 => 'সাতাশি', 88 => 'অষ্টাশি', 89 => 'ঊননব্বই',
        90 => 'নব্বই', 91 => 'একানব্বই', 92 => 'বিরানব্বই', 93 => 'তিরানব্বই', 94 => 'চুরানব্বই', 95 => 'পঁচানব্বই', 96 => 'ছিয়ানব্বই', 97 => 'সাতানব্বই', 98 => 'আটানব্বই', 99 => 'নিরানব্বই',
    ];
    return $words[$n] ?? '';
}

function int_to_words_bn_indian(int $num): string
{
    if ($num <= 0) return '';
    $crore = intdiv($num, 10000000); $num %= 10000000;
    $lakh = intdiv($num, 100000); $num %= 100000;
    $thousand = intdiv($num, 1000); $num %= 1000;
    $hundred = intdiv($num, 100); $num %= 100;
    $rest = $num;

    $parts = [];
    if ($crore > 0) $parts[] = bn_num_word($crore) . ' কোটি';
    if ($lakh > 0) $parts[] = bn_num_word($lakh) . ' লক্ষ';
    if ($thousand > 0) $parts[] = bn_num_word($thousand) . ' হাজার';
    if ($hundred > 0) $parts[] = bn_num_word($hundred) . ' শত';
    if ($rest > 0) $parts[] = bn_num_word($rest);
    return implode(' ', $parts);
}

function number_to_words_bn(float $amount): string
{
    $whole = (int) floor($amount);
    $decimal = (int) round(($amount - $whole) * 100);
    $words = $whole === 0 ? 'শূন্য' : int_to_words_bn_indian($whole);
    $result = trim($words) . ' টাকা';
    if ($decimal > 0) {
        $result .= ' ' . int_to_words_bn_indian($decimal) . ' পয়সা';
    }
    return $result . ' মাত্র';
}

function en_two_digit(int $n): string
{
    static $ones = ['', 'One', 'Two', 'Three', 'Four', 'Five', 'Six', 'Seven', 'Eight', 'Nine', 'Ten',
        'Eleven', 'Twelve', 'Thirteen', 'Fourteen', 'Fifteen', 'Sixteen', 'Seventeen', 'Eighteen', 'Nineteen'];
    static $tens = ['', '', 'Twenty', 'Thirty', 'Forty', 'Fifty', 'Sixty', 'Seventy', 'Eighty', 'Ninety'];
    if ($n < 20) return $ones[$n];
    return trim($tens[intdiv($n, 10)] . ' ' . $ones[$n % 10]);
}

function en_three_digit(int $n): string
{
    static $ones = ['', 'One', 'Two', 'Three', 'Four', 'Five', 'Six', 'Seven', 'Eight', 'Nine'];
    $str = '';
    if ($n >= 100) {
        $str .= $ones[intdiv($n, 100)] . ' Hundred ';
        $n %= 100;
    }
    if ($n > 0) $str .= en_two_digit($n);
    return trim($str);
}

function int_to_words_en_indian(int $num): string
{
    if ($num <= 0) return '';
    $crore = intdiv($num, 10000000); $num %= 10000000;
    $lakh = intdiv($num, 100000); $num %= 100000;
    $thousand = intdiv($num, 1000); $num %= 1000;
    $hundred = $num;

    $parts = [];
    if ($crore > 0) $parts[] = en_three_digit($crore) . ' Crore';
    if ($lakh > 0) $parts[] = en_two_digit($lakh) . ' Lakh';
    if ($thousand > 0) $parts[] = en_two_digit($thousand) . ' Thousand';
    if ($hundred > 0) $parts[] = en_three_digit($hundred);
    return implode(' ', $parts);
}

function number_to_words_en(float $amount): string
{
    $whole = (int) floor($amount);
    $decimal = (int) round(($amount - $whole) * 100);
    $words = $whole === 0 ? 'Zero' : int_to_words_en_indian($whole);
    $result = trim($words) . ' Taka';
    if ($decimal > 0) {
        $result .= ' and ' . int_to_words_en_indian($decimal) . ' Poisha';
    }
    return $result . ' Only';
}

function amount_in_words(float $amount): string
{
    global $CURRENT_LANG;
    return $CURRENT_LANG === 'bn' ? number_to_words_bn($amount) : number_to_words_en($amount);
}

// --- Database convenience wrappers ------------------------------------------
function fetch_all(string $sql, array $params = []): array
{
    $stmt = db()->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll();
}

function fetch_one(string $sql, array $params = []): ?array
{
    $stmt = db()->prepare($sql);
    $stmt->execute($params);
    $row = $stmt->fetch();
    return $row === false ? null : $row;
}

function execute(string $sql, array $params = []): PDOStatement
{
    $stmt = db()->prepare($sql);
    $stmt->execute($params);
    return $stmt;
}

// --- Balance / profit engine -------------------------------------------------
function balance_delta(string $person_type, string $transaction_type, float $amount): float
{
    if ($transaction_type === 'Adjustment') return $amount; // signed, entered by the user
    if ($transaction_type === 'Expense') return 0.0;

    $increases = ($person_type === 'Borrower' && in_array($transaction_type, ['Amount Given', 'Interest'], true))
        || ($person_type === 'Lender' && in_array($transaction_type, ['Amount Received', 'Interest'], true));

    return $increases ? $amount : -$amount;
}

function profit_delta(string $person_type, string $transaction_type, float $amount): float
{
    if ($transaction_type === 'Expense') return -$amount;
    if ($transaction_type !== 'Interest') return 0.0;
    return $person_type === 'Borrower' ? $amount : -$amount;
}

function recalc_person_balance(int $person_id): void
{
    $person = fetch_one('SELECT person_type, opening_balance FROM persons WHERE id = ?', [$person_id]);
    if (!$person) return;

    $balance = (float) $person['opening_balance'];
    $rows = fetch_all(
        'SELECT transaction_type, amount FROM transactions WHERE person_id = ? AND is_deleted = 0',
        [$person_id]
    );
    foreach ($rows as $row) {
        $balance += balance_delta($person['person_type'], $row['transaction_type'], (float) $row['amount']);
    }

    execute('UPDATE persons SET balance = ? WHERE id = ?', [$balance, $person_id]);
}

// --- Flash messages ------------------------------------------------------------
function flash(string $message, string $type = 'success'): void
{
    $_SESSION['flash_message'] = $message;
    $_SESSION['flash_type'] = $type;
}

function redirect(string $url, ?string $message = null, string $type = 'success'): void
{
    if ($message !== null) {
        flash($message, $type);
    }
    header('Location: ' . $url);
    exit;
}

// --- Activity log ------------------------------------------------------------
function log_activity(string $action, string $entity_type = '', int $entity_id = 0, string $details = ''): void
{
    execute(
        'INSERT INTO activity_logs (user_id, action, entity_type, entity_id, details, ip_address) VALUES (?, ?, ?, ?, ?, ?)',
        [$_SESSION['user_id'] ?? null, $action, $entity_type, $entity_id, $details, $_SERVER['REMOTE_ADDR'] ?? null]
    );
}

// --- Notifications -------------------------------------------------------------
function notify(?int $user_id, string $title, string $message, string $type = 'system', ?int $related_person_id = null): void
{
    execute(
        'INSERT INTO notifications (user_id, title, message, type, related_person_id, created_by) VALUES (?, ?, ?, ?, ?, ?)',
        [$user_id, $title, $message, $type, $related_person_id, $_SESSION['user_id'] ?? null]
    );
}

function unread_notification_count(int $user_id): int
{
    $row = fetch_one(
        'SELECT COUNT(*) AS c FROM notifications WHERE (user_id = ? OR user_id IS NULL) AND is_read = 0',
        [$user_id]
    );
    return (int) ($row['c'] ?? 0);
}

// --- CSV export ------------------------------------------------------------------
function output_csv(string $filename, array $headers, array $rows): void
{
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    $out = fopen('php://output', 'w');
    fwrite($out, "\xEF\xBB\xBF"); // UTF-8 BOM so Excel renders Bangla correctly
    fputcsv($out, $headers);
    foreach ($rows as $row) {
        fputcsv($out, $row);
    }
    fclose($out);
    exit;
}

// --- Profit calculation --------------------------------------------------------
function calculate_profit(string $start_date, string $end_date, ?int $location_id = null): array
{
    $locJoin = $location_id ? 'JOIN persons p2 ON p2.id = t.person_id AND p2.location_id = ?' : '';

    $incomeSql = "SELECT COALESCE(SUM(t.amount),0) AS total FROM transactions t
                  JOIN persons p ON p.id = t.person_id AND p.person_type = 'Borrower'
                  $locJoin
                  WHERE t.transaction_type = 'Interest' AND t.is_deleted = 0
                  AND t.transaction_date BETWEEN ? AND ?";
    $expenseInterestSql = "SELECT COALESCE(SUM(t.amount),0) AS total FROM transactions t
                  JOIN persons p ON p.id = t.person_id AND p.person_type = 'Lender'
                  $locJoin
                  WHERE t.transaction_type = 'Interest' AND t.is_deleted = 0
                  AND t.transaction_date BETWEEN ? AND ?";

    $params = $location_id ? [$location_id, $start_date, $end_date] : [$start_date, $end_date];

    $income = (float) (fetch_one($incomeSql, $params)['total'] ?? 0);
    $expenseInterest = (float) (fetch_one($expenseInterestSql, $params)['total'] ?? 0);

    // General expenses (person_id IS NULL) have no location, so only count them
    // when no location filter is active.
    $otherExpense = 0.0;
    if (!$location_id) {
        $row = fetch_one(
            "SELECT COALESCE(SUM(amount),0) AS total FROM transactions
             WHERE transaction_type = 'Expense' AND is_deleted = 0 AND transaction_date BETWEEN ? AND ?",
            [$start_date, $end_date]
        );
        $otherExpense = (float) ($row['total'] ?? 0);
    }

    return [
        'income' => $income,
        'expense_interest' => $expenseInterest,
        'other_expense' => $otherExpense,
        'net' => $income - $expenseInterest - $otherExpense,
    ];
}

// --- Small view helpers ------------------------------------------------------------
function active_locations(): array
{
    return fetch_all('SELECT id, name FROM locations WHERE is_deleted = 0 ORDER BY name');
}

function e(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}
