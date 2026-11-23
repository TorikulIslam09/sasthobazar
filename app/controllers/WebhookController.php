<?php
declare(strict_types=1);

/**
 * Courier status webhooks: POST /webhooks/courier/{code}?token=...  (JSON or form body)
 * No session / CSRF: authenticated by the courier's secret webhook token (Admin > Couriers),
 * or for Pathao by the webhook secret in the X-PATHAO-Signature header (answered with HTTP 202 + the integration header).
 * Unknown parcels are acknowledged (200) so couriers do not retry forever; every call is logged.
 */
class WebhookController extends Controller
{
    public function courier(string $code): void
    {
        $courier = Courier::findByCode($code);
        $cfg = $courier ? Courier::config($courier) : [];
        $token = (string) ($_GET['token'] ?? ($_SERVER['HTTP_X_WEBHOOK_TOKEN'] ?? ''));
        $expected = (string) ($cfg['webhook_token'] ?? '');
        $okToken = $courier && $expected !== '' && hash_equals($expected, $token);
        // Pathao: sends our webhook secret in X-PATHAO-Signature and expects it back in a response header (+ HTTP 202)
        $pathaoSecret = $code === 'pathao' ? trim((string) ($cfg['webhook_secret'] ?? '')) : '';
        $okPathao = $pathaoSecret !== '' && hash_equals($pathaoSecret, (string) ($_SERVER['HTTP_X_PATHAO_SIGNATURE'] ?? ''));
        if (!$courier || (!$okToken && !$okPathao)) {
            $this->json(['ok' => false, 'error' => 'unauthorized'], 401);
        }
        if ($pathaoSecret !== '') {
            header('X-Pathao-Merchant-Webhook-Integration-Secret: ' . $pathaoSecret);
        }
        $raw = (string) file_get_contents('php://input', false, null, 0, 65536);
        $payload = json_decode($raw, true);
        if (!is_array($payload)) {
            $payload = $_POST;
        }
        $events = Courier::adapter($courier)->webhookEvents($payload);
        $done = 0;
        foreach ($events as $ev) {
            if (!$ev['status']) {
                continue;
            }
            $id = $this->db->fetchColumn(
                'SELECT id FROM shipments WHERE courier_id = :c AND (consignment_id = :r1 OR tracking_no = :r2) ORDER BY id DESC LIMIT 1',
                ['c' => (int) $courier['id'], 'r1' => $ev['ref'], 'r2' => $ev['ref']]
            );
            if (!$id) {
                error_log('[courier webhook] ' . $code . ': unknown parcel ' . $ev['ref']);
                continue;
            }
            try {
                if (Shipment::updateStatus((int) $id, $ev['status'], $ev['note'] ?? null, 'webhook', $payload, null)) {
                    $done++;
                }
            } catch (Throwable $e) {
                error_log('[courier webhook] ' . $code . ' #' . $id . ': ' . $e->getMessage());
            }
        }
        $this->json(['ok' => true, 'events' => count($events), 'applied' => $done], $code === 'pathao' ? 202 : 200);
    }
}
