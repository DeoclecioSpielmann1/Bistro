<?php
declare(strict_types=1);
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/funcoes.php';

/* ================= AÇÕES (POST → redirect → GET) ================= */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_ok()) {
        flash('error', 'Sessão expirada. Recarregue a página e tente de novo.');
        redirecionar('index.php');
    }
    $acao = (string)($_POST['acao'] ?? '');

    try {
        if ($acao === 'criar_comanda') {
            $observacao = mb_substr(trim((string)($_POST['observacao'] ?? '')), 0, 2000);

            // quantidades válidas: [id_produto => qtd]
            $quantidades = [];
            $enviado = $_POST['quantidade'] ?? [];
            foreach ((is_array($enviado) ? $enviado : []) as $pid => $qraw) {
                $pid = filter_var($pid, FILTER_VALIDATE_INT);
                $q   = filter_var($qraw, FILTER_VALIDATE_INT);
                if ($pid && $q && $q >= 1 && $q <= 99) { $quantidades[$pid] = $q; }
            }

            if (!$quantidades) {
                flash('error', 'Selecione ao menos um produto e informe uma quantidade maior que zero.');
                redirecionar('index.php');
            }

            ksort($quantidades);
            $pdo->beginTransaction();
            $ph = implode(',', array_fill(0, count($quantidades), '?'));
            $s = $pdo->prepare("SELECT id, nome, preco, estoque FROM produtos WHERE ativo = 1 AND id IN ($ph) ORDER BY id FOR UPDATE");
            $s->execute(array_keys($quantidades));
            $produtos = $s->fetchAll();

            if (count($produtos) !== count($quantidades)) {
                $pdo->rollBack();
                flash('error', 'Um ou mais produtos não estão mais disponíveis. Atualize a página e tente novamente.');
                redirecionar('index.php');
            }

            $faltantes = [];
            foreach ($produtos as $p) {
                $solicitado = $quantidades[(int)$p['id']];
                if ((int)$p['estoque'] < $solicitado) {
                    $faltantes[] = $p['nome'] . ': disponíveis ' . (int)$p['estoque'] . ', solicitado ' . $solicitado;
                }
            }
            if ($faltantes) {
                $pdo->rollBack();
                flash('error', 'Estoque insuficiente — ' . implode('; ', $faltantes) . '.');
                redirecionar('index.php');
            }

            $s = $pdo->prepare('INSERT INTO comandas (observacao) VALUES (?)');
            $s->execute([$observacao !== '' ? $observacao : null]);
            $id = (int)$pdo->lastInsertId();
            $pdo->prepare('UPDATE comandas SET numero = ? WHERE id = ?')->execute([$id, $id]);

            $si = $pdo->prepare('INSERT INTO itens_comanda (comanda_id, produto_id, nome_produto, quantidade, preco_unitario) VALUES (?,?,?,?,?)');
            $baixarEstoque = $pdo->prepare('UPDATE produtos SET estoque = estoque - ? WHERE id = ? AND ativo = 1 AND estoque >= ?');
            foreach ($produtos as $p) {
                $quantidade = $quantidades[(int)$p['id']];
                $baixarEstoque->execute([$quantidade, $p['id'], $quantidade]);
                if ($baixarEstoque->rowCount() !== 1) {
                    throw new RuntimeException('O estoque mudou durante o registro da comanda.');
                }
                $si->execute([$id, $p['id'], $p['nome'], $quantidade, $p['preco']]);
            }
            $pdo->commit();

            flash('success', "Comanda #$id registrada com sucesso.");
            redirecionar('index.php');
        }

        if ($acao === 'entregar') {
            $id = filter_var($_POST['comanda_id'] ?? '', FILTER_VALIDATE_INT);
            $s = $pdo->prepare("UPDATE comandas SET status = 'entregue', data_entrega = NOW() WHERE id = ? AND status = 'aberta'");
            $s->execute([$id ?: 0]);
            $s->rowCount() ? flash('success', 'Comanda marcada como entregue.')
                           : flash('error', 'Comanda não encontrada ou já entregue.');
            redirecionar('index.php');
        }

        if ($acao === 'observacao') {
            $id  = filter_var($_POST['comanda_id'] ?? '', FILTER_VALIDATE_INT);
            $obs = mb_substr(trim((string)($_POST['observacao'] ?? '')), 0, 2000);
            $s = $pdo->prepare("UPDATE comandas SET observacao = ? WHERE id = ? AND status = 'aberta'");
            $s->execute([$obs !== '' ? $obs : null, $id ?: 0]);
            flash('success', 'Observação atualizada.');
            redirecionar('index.php');
        }

        flash('error', 'Ação desconhecida.');
        redirecionar('index.php');
    } catch (Throwable $ex) {
        if ($pdo->inTransaction()) { $pdo->rollBack(); }
        error_log('Erro nas comandas: ' . $ex->getMessage());
        flash('error', 'Não foi possível concluir a operação. Confira o banco e a configuração.');
        redirecionar('index.php');
    }
}

/* ================= DADOS DA TELA ================= */
$flash    = flash_ler();
$produtos = $pdo->query('SELECT id, nome, categoria, preco, estoque FROM produtos WHERE ativo = 1 ORDER BY categoria, nome')->fetchAll();
$comandas = $pdo->query(
    'SELECT c.*, COALESCE(SUM(i.quantidade * i.preco_unitario), 0) AS total, COALESCE(SUM(i.quantidade), 0) AS unidades
       FROM comandas c LEFT JOIN itens_comanda i ON i.comanda_id = c.id
      GROUP BY c.id ORDER BY c.id DESC LIMIT 30'
)->fetchAll();

$itensPor = [];
if ($comandas) {
    $ids = array_map(fn($c) => (int)$c['id'], $comandas);
    $ph  = implode(',', array_fill(0, count($ids), '?'));
    $s = $pdo->prepare("SELECT * FROM itens_comanda WHERE comanda_id IN ($ph) ORDER BY id");
    $s->execute($ids);
    foreach ($s->fetchAll() as $i) { $itensPor[$i['comanda_id']][] = $i; }
}

/* ================= TELA ================= */
$titulo = 'Comandas'; $pagina = 'comandas';
$eyebrow = 'OPERAÇÃO DA COZINHA'; $h1 = 'Comandas do restaurante';
$descricao = 'Registre pedidos, confira o estoque e imprima comandas para a cozinha.'; $icone = '☕';
require __DIR__ . '/includes/topo.php';
?>
<section class="layout">
  <div class="panel">
    <div class="panel-heading"><div><span class="step">01 / NOVO PEDIDO</span><h2>Abrir comanda</h2></div><span class="pill">Cozinha</span></div>
    <form method="post">
      <?= csrf_campo() ?>
      <input type="hidden" name="acao" value="criar_comanda">
      <?php if (!$produtos): ?>
        <div class="empty">Nenhum produto ativo. Cadastre produtos no painel <a href="produtos.php">Produtos</a>.</div>
      <?php else: ?>
        <div class="product-list">
          <?php $cat = ''; foreach ($produtos as $p): ?>
            <?php if ($cat !== $p['categoria']): $cat = $p['categoria']; ?><div class="category-label"><?= e($cat) ?></div><?php endif; ?>
            <label class="product-row">
              <span class="product-info">
                <strong><?= e($p['nome']) ?></strong>
                <small><?= brl($p['preco']) ?></small>
                <small class="<?= (int)$p['estoque'] > 0 ? 'stock-available' : 'stock-empty' ?>">
                  <?= (int)$p['estoque'] > 0 ? 'Estoque: ' . (int)$p['estoque'] : 'Esgotado' ?>
                </small>
              </span>
              <span class="quantity"><span>Qtd.</span>
                <input type="number" name="quantidade[<?= (int)$p['id'] ?>]" min="0" max="<?= min(99, (int)$p['estoque']) ?>" value="0" inputmode="numeric" <?= (int)$p['estoque'] < 1 ? 'disabled' : '' ?> aria-label="Quantidade de <?= e($p['nome']) ?>">
              </span>
            </label>
          <?php endforeach; ?>
        </div>
        <label class="field-label" for="obs">Observação geral da cozinha</label>
        <textarea id="obs" name="observacao" rows="3" maxlength="2000" placeholder="Ex.: sem cebola, molho à parte..."></textarea>
        <button class="button primary full" type="submit">＋ Registrar comanda</button>
      <?php endif; ?>
    </form>
    <p class="hint">O estoque é validado e baixado ao registrar o pedido. O preço fica salvo na comanda para preservar o histórico.</p>
  </div>

  <div class="orders-column">
    <div class="section-heading"><div><span class="step">02 / ACOMPANHAMENTO</span><h2>Últimas comandas</h2></div><span class="count"><?= count($comandas) ?> / 30</span></div>
    <?php if (!$comandas): ?>
      <div class="panel empty large">Nenhuma comanda registrada ainda.<br><span>Use o formulário para criar o primeiro pedido.</span></div>
    <?php else: foreach ($comandas as $c): $entregue = $c['status'] === 'entregue'; ?>
      <article id="comanda-<?= (int)$c['id'] ?>" class="order-card <?= $entregue ? 'order-done' : '' ?>">
        <div class="order-top">
          <div><span class="order-number">COMANDA #<?= (int)$c['numero'] ?></span><div class="order-date"><?= e(data_br($c['data_abertura'])) ?></div></div>
          <span class="status <?= $entregue ? 'status-done' : 'status-open' ?>"><?= $entregue ? '✓ Entregue' : '● Em preparo' ?></span>
        </div>
        <div class="order-items">
          <?php foreach ($itensPor[$c['id']] ?? [] as $i): ?>
            <div class="order-item"><span><b><?= (int)$i['quantidade'] ?>×</b> <?= e($i['nome_produto']) ?></span><strong><?= brl($i['quantidade'] * $i['preco_unitario']) ?></strong></div>
          <?php endforeach; ?>
        </div>
        <div class="order-total"><span><?= (int)$c['unidades'] ?> item(ns)</span><strong><?= brl($c['total']) ?></strong></div>
        <?php if ($c['observacao']): ?><div class="observation"><b>Observação:</b> <?= nl2br(e($c['observacao'])) ?></div><?php endif; ?>

        <?php if ($entregue): ?>
          <div class="delivered-note">Entregue em <?= e(data_br($c['data_entrega'])) ?></div>
        <?php else: ?>
          <div class="order-actions">
            <form method="post" onsubmit="return confirm('Marcar comanda #<?= (int)$c['numero'] ?> como entregue?')">
              <?= csrf_campo() ?><input type="hidden" name="acao" value="entregar"><input type="hidden" name="comanda_id" value="<?= (int)$c['id'] ?>">
              <button class="button done-button" type="submit">✓ Marcar entregue</button>
            </form>
            <details><summary>Editar observação</summary>
              <form method="post" class="edit-note">
                <?= csrf_campo() ?><input type="hidden" name="acao" value="observacao"><input type="hidden" name="comanda_id" value="<?= (int)$c['id'] ?>">
                <textarea name="observacao" rows="2" maxlength="2000"><?= e($c['observacao']) ?></textarea>
                <button class="button secondary" type="submit">Salvar observação</button>
              </form>
            </details>
          </div>
        <?php endif; ?>
        <div class="print-actions">
          <button class="button print-button" type="button" onclick="imprimirComanda('comanda-<?= (int)$c['id'] ?>')">Imprimir pedido</button>
        </div>
        <div class="print-heading"><strong>Bistrô Sabor da Vila</strong><span>Comanda #<?= (int)$c['numero'] ?></span></div>
      </article>
    <?php endforeach; endif; ?>
  </div>
</section>
<script>
function imprimirComanda(id) {
  document.querySelectorAll('.order-card.print-selected').forEach(function (card) {
    card.classList.remove('print-selected');
  });
  var comanda = document.getElementById(id);
  if (!comanda) {
    return;
  }
  comanda.classList.add('print-selected');
  document.body.classList.add('printing-order');
  window.addEventListener('afterprint', function () {
    comanda.classList.remove('print-selected');
    document.body.classList.remove('printing-order');
  }, { once: true });
  window.print();
}
</script>
<?php require __DIR__ . '/includes/rodape.php'; ?>
