<?php
declare(strict_types=1);
date_default_timezone_set('America/Sao_Paulo');
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

function e($v): string { return htmlspecialchars((string)($v ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
function brl($v): string { return 'R$ ' . number_format((float)$v, 2, ',', '.'); }
function data_br(?string $dt): string { return $dt ? date('d/m/Y H:i', strtotime($dt)) : '-'; }

/* ---- proteção contra requisições forjadas (CSRF) ---- */
function csrf_token(): string {
    if (empty($_SESSION['csrf'])) { $_SESSION['csrf'] = bin2hex(random_bytes(32)); }
    return $_SESSION['csrf'];
}
function csrf_campo(): string { return '<input type="hidden" name="csrf" value="' . e(csrf_token()) . '">'; }
function csrf_ok(): bool {
    return isset($_POST['csrf'], $_SESSION['csrf']) && hash_equals($_SESSION['csrf'], (string)$_POST['csrf']);
}

/* ---- mensagem de uma única exibição (padrão POST → redirect → GET) ---- */
function flash(string $tipo, string $msg): void { $_SESSION['flash'] = ['tipo' => $tipo, 'msg' => $msg]; }
function flash_ler(): ?array { $f = $_SESSION['flash'] ?? null; unset($_SESSION['flash']); return $f; }
function redirecionar(string $url): void { header('Location: ' . $url); exit; }
