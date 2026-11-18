<?php
/* Espera: $titulo, $pagina ('comandas'|'produtos'), $eyebrow, $h1, $descricao, $icone, $flash (opcional) */
?>
<!doctype html>
<html lang="pt-BR">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title><?= e($titulo) ?> | Bistrô Sabor da Vila</title>
<link rel="stylesheet" href="assets/style.css">
<link rel="stylesheet" href="assets/functionalities.css">
</head>
<body>
<header class="topbar"><div class="wrap topbar-inner">
  <a class="brand" href="index.php"><span class="brand-icon">SV</span><span>Bistrô <b>Sabor da Vila</b><small>PAINEL DE COMANDAS</small></span></a>
  <nav>
    <a <?= $pagina === 'comandas' ? 'class="nav-active"' : '' ?> href="index.php">Comandas</a>
    <a <?= $pagina === 'produtos' ? 'class="nav-active"' : '' ?> href="produtos.php">Produtos</a>
  </nav>
</div></header>
<main class="wrap">
<section class="hero">
  <div><span class="eyebrow"><?= e($eyebrow) ?></span><h1><?= e($h1) ?></h1><p><?= e($descricao) ?></p></div>
  <div class="hero-mark"><?= $icone ?></div>
</section>
<?php if (!empty($flash)): ?>
  <div class="alert <?= $flash['tipo'] === 'error' ? 'error' : 'success' ?>" role="status"><?= e($flash['msg']) ?></div>
<?php endif; ?>
