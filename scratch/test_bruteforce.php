<?php
// scratch/test_bruteforce.php — Teste do sistema anti-brute-force

require_once __DIR__ . '/../api/security_helpers.php';

echo "=== TESTANDO PROTEÇÃO ANTI-HACKER / BRUTE-FORCE (5 TENTATIVAS) ===\n\n";

$testIp = '192.168.1.99';
meckClearFailedLoginAttempts($testIp);

for ($i = 1; $i <= 5; $i++) {
    $res = meckRecordFailedLoginAttempt($testIp);
    echo "Tentativa $i: Falhas = {$res['failed_count']} | Bloqueado = " . ($res['is_now_blocked'] ? 'SIM 🚫' : 'NÃO') . " | Tentativas Restantes = {$res['remaining_attempts']}\n";
}

$check = meckCheckIpLoginAttempts($testIp);
echo "\nStatus Final para IP $testIp:\n";
echo "Bloqueado? " . ($check['blocked'] ? 'SIM 🚫' : 'NÃO') . "\n";
echo "Mensagem: " . ($check['message'] ?? 'Nenhuma') . "\n\n";

meckClearFailedLoginAttempts($testIp);
echo "✅ Teste concluído com sucesso. Histórico de IP limpo.\n";
