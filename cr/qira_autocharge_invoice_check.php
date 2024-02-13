<?php
ini_set('error_reporting', E_ALL);
// session_start();

$path = __DIR__ . DIRECTORY_SEPARATOR;
$prefix = '';
while(!file_exists($path . $prefix . 'autoloader.php')){
    $prefix .= '..' . DIRECTORY_SEPARATOR;
}
define('ROOT_PATH', realpath($path . $prefix));
require_once (ROOT_PATH . DIRECTORY_SEPARATOR . 'autoloader.php');

use Qira;

class Init extends Backend {

    function fetch() {

        // $action = $this->request->get('action');
        $action = 'check_all';

        if ($action == 'check_all') {
            $pm_qira_ids = $this->getPaymentMethodsQiraAchIds('all');
        } else {
            $pm_qira_ids = $this->getPaymentMethodsQiraAchIds('ach');
        }
        if(!empty($pm_qira_ids)) {

            $query = $this->db->placehold("SELECT 
                    o.id,
                    o.date,
                    o.last_checked_date,
                    o.contract_id,
                    o.booking_id,
                    o.payment_method_id,
                    o.paid,
                    o.status,
                    o.status_code,
                    o.transaction_id,
                    o.payment_payer_id,
                    o.payer_id,
                    o.type,
                    o.deposit,
                    l.data as log_data
                FROM __orders o
                LEFT JOIN __logs l ON l.parent_id=o.id AND l.type=3 AND l.subtype=13
                WHERE 
                    l.type in (3)
                    AND l.subtype in (13)
                    AND l.parent_id>0
                GROUP BY o.id
                ORDER BY o.id
                LIMIT 300
            ");
            $this->db->query($query);
            $orders = $this->request->array_to_key($this->db->results(), 'id');

            if (!empty($orders)) {


                $this->db->query("SELECT * FROM __payment_methods AS pm WHERE pm.module='Qira' AND pm.enabled=1");
                $payment_methods_qira = $this->db->results();

                $pm_qira_ids = [];
                if (!empty($payment_methods_qira)) {
                    foreach ($payment_methods_qira as $pm) {
                        if (!empty($pm->settings)) {
                            $pm->settings = unserialize($pm->settings);
                        }
                    }
                }
                $n_order = 0;
                foreach ($orders as $order) {
                    // add autocharge label
                    $this->orders->add_order_labels($order->id, 21);

                    if (!empty($order->log_data)) {
                        $order->log_data = json_decode($order->log_data);

                        if (!empty($order->log_data->request->PayeeId)) {
                            foreach ($payment_methods_qira as $pm) {
                                if (
                                    empty($order->payment_method_id) &&
                                    $order->log_data->request->PayeeId == $pm->settings['payee_id'] &&
                                    $order->log_data->request->PaymentMethodType == $pm->settings['payment_method_type']
                                ) {

                                    $this->orders->update_order($order->id, [
                                        'payment_method_id' => $pm->id
                                    ]);
                                    $n_order ++;
                                    echo $n_order.'. Order '.$order->id.'<br>';
                                    echo ' Payment method: '.$pm->name.'[ID '.$pm->id.'}<br>';
                                    echo '---------------------------<br>';
                                }
                            }
                        }
                    }
                }
            }
            exit;

            if (!empty($orders)) {
                $this->checkInvoices($orders);
            }
        }
    }


    function getPaymentMethodsQiraAchIds ($type) {
        $this->db->query("SELECT * FROM __payment_methods AS pm WHERE pm.module='Qira' AND pm.enabled=1");
        $payment_methods_qira = $this->db->results();

        $pm_qira_ids = [];
        if (!empty($payment_methods_qira)) {
            foreach ($payment_methods_qira as $pm) {
                if (!empty($pm->settings)) {
                    $pm->settings = unserialize($pm->settings);
                    if ($type == 'all' || ($type == 'ach' && $pm->settings['payment_method_type'] == 3)) {
                        $pm_qira_ids[] = $pm->id;
                    }
                }
            }
        }
        return $pm_qira_ids;
    }

    function checkInvoices($orders) {
        if (empty($orders))
            return false;

        $date_today = date("Y-m-d");
        $qira = new Qira();
        $n_order = 0;
        $n_qira = 0;
        foreach ($orders as $order) {
            $n_order++;
            echo $n_order.'. Check Qira status for Order '.$order->id.'<br>';
            echo 'Status code: '.(!is_null($order->status_code)? $order->status_code : 'NULL');

            $payment_result = $qira->getPayerPaymentOne($order->payment_payer_id, $order->transaction_id);

            if (!empty($payment_result)) {
                if(!empty($payment_result->PaymentResult->TransactionPayOutDate)) {
                    $payout_dt = new DateTime($payment_result->PaymentResult->TransactionPayOutDate);
                    $payout_date = $payout_dt->format('Y-m-d H:i:s');
                }

                echo ' => '.$payment_result->PaymentResult->TransactionStatusCode.'<br>';
                $n_qira++;
                echo 'Qira request: '.$n_qira.'<br>';

                // Paid
                if (in_array($payment_result->PaymentResult->TransactionStatusCode, [
                    0,   // Approved
                    800  // Paidout
                ]) && $payment_result->Success == 1) {

                    if ((is_null($order->status_code) || in_array($order->status_code, [0, 800])) && $order->paid == 1) {
                        $order_upd = [
                            'last_checked_date' => $date_today
                        ];
                        if ($order->status_code != $payment_result->PaymentResult->TransactionStatusCode) {
                            $order_upd['status_code'] = $payment_result->PaymentResult->TransactionStatusCode;
                        }
                        $this->orders->update_order($order->id, $order_upd);
                        echo 'Order has been paid<br>';
                    } else {
                        $this->orders->update_order($order->id, [
                            'paid' => 1,
                            'status' => 2,
                            'status_code' => $payment_result->PaymentResult->TransactionStatusCode,
                            'payment_date' => $payout_date,
                            'last_checked_date' => $date_today
                        ]);
                        $this->logs->add_log([
                            'parent_id' => $order->id,
                            'type' => 3,
                            'subtype' => 5, // order paid
                            'user_id' => $order->payer_id,
                            'sender_type' => 3,
                            'value' => $payment_result->PaymentResult->TransactionStatusText.'<br>Transaction PayOut Date: '.$payout_date
                        ]);

                        if ($order->type == 1 && !empty($order->booking_id) && $order->deposit == 0) {
                            $booking = $this->beds->get_bookings([
                                'id' => $order->booking_id,
                                'limit' => 1
                            ]);

                            // Not Canceled
                            if (!empty($booking) && $booking->status != 0) {
                                $this->beds->update_due_booking($booking->id, [
                                    'initiator' => 'payment',
                                    'order_id' => $order->id,
                                    'payment_method' => 'Qira',
                                    'payment_status' => 'succeeded' // succeeded, pending
                                ]);
                                echo 'Update booking: '.$booking->id.'<br>';
                            }
                        }
                    }
                }
                // Pending
                elseif (in_array($payment_result->PaymentResult->TransactionStatusCode, [
                    703, // Pending_Settlement
                    700  // Settled
                ])) {
                    $this->orders->update_order($order->id, [
                        'last_checked_date' => $date_today,
                        'status_code' => $payment_result->PaymentResult->TransactionStatusCode
                    ]);
                    if ($payment_result->PaymentResult->TransactionStatusCode != $order->status_code) {
                        $this->logs->add_log([
                            'parent_id' => $order->id,
                            'type' => 3,
                            'subtype' => 4,
                            'user_id' => $order->payer_id,
                            'sender_type' => 3,
                            'value' => 'Pending: '.$payment_result->PaymentResult->TransactionStatusText
                        ]);
                    }
                }
                else {
                    // Failed
                    $this->orders->update_order($order->id, [
                        'status' => 4,
                        'status_code' => $payment_result->PaymentResult->TransactionStatusCode,
                        'last_checked_date' => $date_today
                    ]);
                    $this->logs->add_log([
                        'parent_id' => $order->id,
                        'type' => 3,
                        'subtype' => 4,
                        'user_id' => $order->payer_id,
                        'sender_type' => 3,
                        'value' => $payment_result->PaymentResult->TransactionStatusText
                    ]);
                }
            }
            else {
                echo '<br>Empty response in Order '.$order->id.'<br>';
            }
            echo '--------------------------<br>';
        }


    }
}

$init = new Init();
$init->fetch();