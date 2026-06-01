<?php
    class CryptomusGateway
    {
        public function info()
        {
            return [
                'title'        => 'Cryptomus Gateway',
                'logo'         => 'assets/logo.jpg',
                'currency'     => 'USD',
                'tab'          => 'global',

                'gateway_type' => 'api',
            ];
        }

        public function color()
        {
            return [
                'primary_color'  => '#02c076',
                'text_color'     => '#FFFFFF',
                'btn_color'      => '#02c076',
                'btn_text_color' => '#FFFFFF',
            ];
        }

        public function fields()
        {
            return [
                [
                    'name'  => 'merchant_uuid',
                    'label' => 'Cryptomus Merchant UUID',
                    'type'  => 'text',
                ],
                [
                    'name'  => 'payment_api_key',
                    'label' => 'Cryptomus Payment API Key',
                    'type'  => 'text',
                ],
            ];
        }

        /**
         * Generate the HMAC signature required by Cryptomus API.
         * Sign = MD5( base64_encode(json_encode($data)) + $apiKey )
         */
        private function generate_sign($data, $apiKey)
        {
            return md5(base64_encode(json_encode($data, JSON_UNESCAPED_UNICODE)) . $apiKey);
        }

        function process_payment($data = [])
        {
            echo '<center><div class="spinner-border text-primary m-3 loading-123412341234" role="status"><span class="visually-hidden">Loading...</span></div></center>';

            $merchantUuid  = $data['options']['merchant_uuid']  ?? '';
            $paymentApiKey = $data['options']['payment_api_key'] ?? '';

            $success_url  = pp_callback_url();
            $cancel_url   = pp_checkout_address();

            // order_id must only contain letters, numbers, underscores, dashes (max 128 chars)
            $order_id = preg_replace('/[^a-zA-Z0-9_\-]/', '_', $data['transaction']['ref']);

            $payload = [
                'amount'       => (string) $data['transaction']['local_net_amount'],
                'currency'     => strtoupper($data['transaction']['local_currency']),
                'order_id'     => $order_id,
                'url_return'   => $cancel_url,
                'url_success'  => $success_url,
                'url_callback' => pp_callback_url(),   // webhook for server-side confirmation
                'lifetime'     => 3600,                // invoice expires in 1 hour
            ];

            $sign = $this->generate_sign($payload, $paymentApiKey);

            $ch = curl_init("https://api.cryptomus.com/v1/payment");
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Content-Type: application/json',
                'merchant: ' . $merchantUuid,
                'sign: '     . $sign,
            ]);

            $response = curl_exec($ch);
            curl_close($ch);

            $result = json_decode($response, true);

            if (isset($result['result']['url'])) {
                // Redirect user to Cryptomus hosted payment page
                echo '<script>location.href="' . $result['result']['url'] . '";</script>';
            } else {
                $error = $result['message'] ?? ($result['errors'] ? json_encode($result['errors']) : $response);
                echo "<center>Error creating invoice: " . htmlspecialchars($error) . "</center>";
                echo "<style>.loading-123412341234{display: none;}</style>";
            }
        }

        function callback($data = [])
        {
            echo '<center><div class="spinner-border text-primary m-3 loading-123412341234" role="status"><span class="visually-hidden">Loading...</span></div></center>';

            $paymentApiKey = $data['options']['payment_api_key'] ?? '';

            // Read raw POST body (Cryptomus sends JSON webhook)
            $raw  = file_get_contents('php://input');
            $body = json_decode($raw, true);

            // ── If this is a webhook (server-to-server POST from Cryptomus) ──
            if (!empty($body) && isset($body['sign'])) {
                $receivedSign = $body['sign'];
                unset($body['sign']);

                // Verify signature
                $expectedSign = md5(base64_encode(json_encode($body, JSON_UNESCAPED_UNICODE)) . $paymentApiKey);

                if (!hash_equals($expectedSign, $receivedSign)) {
                    http_response_code(400);
                    echo "Invalid signature.";
                    return;
                }

                $status   = $body['status']   ?? '';
                $order_id = $body['order_id'] ?? '';
                $txid     = $body['txid']     ?? '';
                $uuid     = $body['uuid']     ?? '';

                // Reverse the order_id sanitisation done in process_payment
                $ref = str_replace('_', $data['transaction']['ref'][0], $order_id);
                // Simpler: compare directly since we used the ref as the base
                if ($order_id !== preg_replace('/[^a-zA-Z0-9_\-]/', '_', $data['transaction']['ref'])) {
                    http_response_code(200); // Acknowledge but ignore unrelated webhook
                    echo "OK";
                    return;
                }

                if (in_array($status, ['paid', 'paid_over', 'confirm_check'])) {
                    $moreinfo = [
                        [
                            'label' => 'Cryptomus Invoice UUID',
                            'value' => $uuid,
                        ],
                        [
                            'label' => 'Transaction ID (txid)',
                            'value' => $txid ?: 'N/A (internal transfer)',
                        ],
                        [
                            'label' => 'Payment Status',
                            'value' => $status,
                        ],
                    ];

                    pp_set_transaction_status(
                        $data['transaction']['ref'],
                        'completed',
                        $data['gateway']['gateway_id'],
                        $txid ?: $uuid,
                        $moreinfo
                    );

                    http_response_code(200);
                    echo "OK";
                } else {
                    // Payment failed / cancelled
                    http_response_code(200);
                    echo "OK";
                }

                return;
            }

            // ── Browser redirect callback (user returned after payment) ──
            // Poll Cryptomus API to check payment status by order_id
            $merchantUuid  = $data['options']['merchant_uuid']  ?? '';
            $order_id      = preg_replace('/[^a-zA-Z0-9_\-]/', '_', $data['transaction']['ref']);

            $query   = ['order_id' => $order_id];
            $sign    = $this->generate_sign($query, $paymentApiKey);

            $ch = curl_init("https://api.cryptomus.com/v1/payment/info");
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($query));
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Content-Type: application/json',
                'merchant: ' . $merchantUuid,
                'sign: '     . $sign,
            ]);

            $response = curl_exec($ch);
            curl_close($ch);

            $result = json_decode($response, true);
            $info   = $result['result'] ?? [];

            $status = $info['payment_status'] ?? ($info['status'] ?? '');

            if (in_array($status, ['paid', 'paid_over', 'confirm_check'])) {
                $txid = $info['txid'] ?? '';
                $uuid = $info['uuid'] ?? '';

                $moreinfo = [
                    [
                        'label' => 'Cryptomus Invoice UUID',
                        'value' => $uuid,
                    ],
                    [
                        'label' => 'Transaction ID (txid)',
                        'value' => $txid ?: 'N/A (internal transfer)',
                    ],
                    [
                        'label' => 'Payment Status',
                        'value' => $status,
                    ],
                ];

                pp_set_transaction_status(
                    $data['transaction']['ref'],
                    'completed',
                    $data['gateway']['gateway_id'],
                    $txid ?: $uuid,
                    $moreinfo
                );

                echo "<script>location.reload();</script>";
            } else {
                echo "<center>Payment not completed or still pending. Status: " . htmlspecialchars($status) . "</center>";
                echo "<style>.loading-123412341234{display: none;}</style>";
            }
        }
    }
