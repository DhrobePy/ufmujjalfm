<?php
require_once __DIR__ . '/../core/init.php';

if (!is_logged_in()) {
    exit('Unauthorized');
}

$loan_id = (int)$_GET['loan_id'];

// Get loan details
$stmt = $pdo->prepare('
    SELECT l.*, e.first_name, e.last_name
    FROM loans l
    JOIN employees e ON l.employee_id = e.id
    WHERE l.id = ?
');
$stmt->execute([$loan_id]);
$loan = $stmt->fetch();

if (!$loan) {
    exit('Loan not found');
}

// Get installment history
$stmt = $pdo->prepare('
    SELECT * FROM loan_installments 
    WHERE loan_id = ? 
    ORDER BY payment_date DESC
');
$stmt->execute([$loan_id]);
$installments = $stmt->fetchAll();

// Calculate totals
$total_paid = array_sum(array_column($installments, 'amount'));
$remaining = $loan['amount'] - $total_paid;
?>

<div class="mb-3">
    <h6><?php echo htmlspecialchars($loan['first_name'] . ' ' . $loan['last_name']); ?></h6>
    <p class="mb-1"><strong>Loan Amount:</strong> $<?php echo number_format($loan['amount'], 2); ?></p>
    <p class="mb-1"><strong>Total Paid:</strong> $<?php echo number_format($total_paid, 2); ?></p>
    <p class="mb-1"><strong>Remaining:</strong> $<?php echo number_format($remaining, 2); ?></p>
</div>

<?php if (empty($installments)): ?>
    <div class="alert alert-info">No payments recorded yet.</div>
<?php else: ?>
    <div class="table-responsive">
        <table class="table table-sm">
            <thead>
                <tr>
                    <th>Date</th>
                    <th>Amount</th>
                    <th>Running Balance</th>
                </tr>
            </thead>
            <tbody>
                <?php 
                $running_balance = $loan['amount'];
                foreach ($installments as $installment): 
                    $running_balance -= $installment['amount'];
                ?>
                    <tr>
                        <td><?php echo date('M d, Y', strtotime($installment['payment_date'])); ?></td>
                        <td>$<?php echo number_format($installment['amount'], 2); ?></td>
                        <td>$<?php echo number_format($running_balance, 2); ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
<?php endif; ?>
