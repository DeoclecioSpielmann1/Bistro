<?php
declare(strict_types=1);
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/funcoes.php';

$erro = '';
$form = ['id' => 0, 'nome' => '', 'categoria' => 'Pratos feitos', 'preco' => '', 'estoque' => 0, 'descricao' => '', 'ativo' => 1];

/* ================= AÇÕES ================= */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_ok()) {
        flash('error', 'Sessão expirada. Recarregue a página e tente de novo.');
        redirecionar('produtos.php');
    }
    $acao = (string)($_POST['acao'] ?? '');

    try {
        if ($acao === 'salvar') {
            $estoqueRaw = $_POST['estoque'] ?? '';
            $estoqueTexto = is_scalar($estoqueRaw) ? trim((string)$estoqueRaw) : '';
            $estoque = filter_var($estoqueTexto, FILTER_VALIDATE_INT);
            $form = [
                'id'        => (int)(filter_var($_POST['id'] ?? '', FILTER_VALIDATE_INT) ?: 0),
                'nome'      => trim((string)($_POST['nome'] ?? '')),
                'categoria' => trim((string)($_POST['categoria'] ?? '')),
                'preco'     => trim(str_replace(',', '.', (string)($_POST['preco'] ?? ''))),
                'estoque'   => $estoque === false ? $estoqueTexto : $estoque,
                'descricao' => trim((string)($_POST['descricao'] ?? '')),
                'ativo'     => isset($_POST['ativo']) ? 1 : 0,
            ];

            if ($form['nome'] === '' || mb_strlen($form['nome']) > 120) {
                $erro = 'Informe o nome do produto (até 120 caracteres).';
            } elseif ($form['categoria'] === '' || mb_strlen($form['categoria']) > 60) {
                $erro = 'Informe uma categoria (até 60 caracteres).';
            } elseif (!preg_match('/^\d{1,8}(\.\d{1,2})?$/', $form['preco'])) {
                $erro = 'Informe um preço válido (ex.: 24.90).';
            } elseif ($estoque === false || $estoque < 0 || $estoque > 999999) {
                $erro = 'Informe um estoque inteiro entre 0 e 999999.';
            } elseif (mb_strlen($form['descricao']) > 255) {
                $erro = 'A descrição pode ter no máximo 255 caracteres.';
            } else {
                $form['estoque'] = $estoque;
                // nome repetido (a comparação do MySQL ignora maiúsculas/acentos)
                $s = $pdo->prepare('SELECT id FROM produtos WHERE nome = ? AND id <> ?');
                $s->execute([$form['nome'], $form['id']]);
                if ($s->fetch()) {
                    $erro = 'Já existe um produto com esse nome.';
                } elseif ($form['id']) {
                    $s = $pdo->prepare('SELECT id FROM produtos WHERE id = ?');
                    $s->execute([$form['id']]);
                    if (!$s->fetch()) {
                        $erro = 'Produto não encontrado.';
                    } else {
                        $pdo->prepare('UPDATE produtos SET nome=?, descricao=?, categoria=?, preco=?, ativo=?, estoque=? WHERE id=?')
                            ->execute([$form['nome'], $form['descricao'] ?: null, $form['categoria'], $form['preco'], $form['ativo'], $form['estoque'], $form['id']]);
                        flash('success', 'Produto atualizado.');
                        redirecionar('produtos.php');
                    }
                } else {
                    $pdo->prepare('INSERT INTO produtos (nome, descricao, categoria, preco, ativo, estoque) VALUES (?,?,?,?,?,?)')
                        ->execute([$form['nome'], $form['descricao'] ?: null, $form['categoria'], $form['preco'], $form['ativo'], $form['estoque']]);
                    flash('success', 'Produto cadastrado.');
                    redirecionar('produtos.php');
                }
            }
        } elseif ($acao === 'alternar') {
            $id = filter_var($_POST['id'] ?? '', FILTER_VALIDATE_INT);
            $s = $pdo->prepare('UPDATE produtos SET ativo = IF(ativo = 1, 0, 1) WHERE id = ?');
            $s->execute([$id ?: 0]);
            flash('success', 'Disponibilidade atualizada.');
            redirecionar('produtos.php');
        }
    } catch (Throwable $ex) {
        error_log('Erro produtos: ' . $ex->getMessage());
        $erro = 'Não foi possível salvar a alteração.';
    }
}

/* ================= MODO EDIÇÃO (?editar=ID) ================= */
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['editar'])) {
    $id = filter_var($_GET['editar'], FILTER_VALIDATE_INT);
    if ($id) {
        $s = $pdo->prepare('SELECT * FROM produtos WHERE id = ?');
        $s->execute([$id]);
        if ($p = $s->fetch()) {
            $form = ['id' => (int)$p['id'], 'nome' => $p['nome'], 'categoria' => $p['categoria'],
                     'preco' => $p['preco'], 'estoque' => (int)$p['estoque'],
                     'descricao' => (string)$p['descricao'], 'ativo' => (int)$p['ativo']];
        }
    }
}

$flash = flash_ler();
if ($erro) { $flash = ['tipo' => 'error', 'msg' => $erro]; }
$produtos = $pdo->query('SELECT * FROM produtos ORDER BY categoria, nome')->fetchAll();
$editando = $form['id'] > 0;

/* ================= TELA ================= */
$titulo = 'Produtos'; $pagina = 'produtos';
$eyebrow = 'CARDÁPIO E PREÇOS'; $h1 = 'Cadastro de produtos';
$descricao = 'Gerencie o cardápio, ajuste preços e mantenha o estoque disponível atualizado.'; $icone = '♨';
require __DIR__ . '/includes/topo.php';
?>
<section class="product-admin-grid">
  <div class="panel">
    <div class="panel-heading"><div>
      <span class="step"><?= $editando ? 'EDITAR PRODUTO' : 'NOVO CADASTRO' ?></span>
      <h2><?= $editando ? 'Editar produto' : 'Adicionar produto' ?></h2>
    </div></div>
    <form method="post" class="admin-form">
      <?= csrf_campo() ?>
      <input type="hidden" name="acao" value="salvar">
      <input type="hidden" name="id" value="<?= (int)$form['id'] ?>">
      <label>Nome do produto<input name="nome" required maxlength="120" value="<?= e($form['nome']) ?>" placeholder="Ex.: Lasanha à bolonhesa"></label>
      <label>Categoria<input name="categoria" required maxlength="60" value="<?= e($form['categoria']) ?>" placeholder="Ex.: Pratos feitos" list="categorias"></label>
      <datalist id="categorias"><?php foreach (array_unique(array_column($produtos, 'categoria')) as $c): ?><option value="<?= e($c) ?>"><?php endforeach; ?></datalist>
      <label>Preço (R$)<input name="preco" type="number" required min="0" max="99999999" step="0.01" inputmode="decimal" value="<?= e($form['preco']) ?>" placeholder="0.00"></label>
      <label>Estoque disponível (unidades)<input name="estoque" type="number" required min="0" max="999999" step="1" inputmode="numeric" value="<?= e($form['estoque']) ?>" placeholder="0"></label>
      <label>Descrição <span class="muted">(opcional)</span><textarea name="descricao" rows="3" maxlength="255" placeholder="Descrição breve"><?= e($form['descricao']) ?></textarea></label>
      <label class="check-line"><input type="checkbox" name="ativo" value="1" <?= $form['ativo'] ? 'checked' : '' ?>> Produto disponível para novas comandas</label>
      <button class="button primary full" type="submit"><?= $editando ? 'Salvar alterações' : 'Cadastrar produto' ?></button>
      <?php if ($editando): ?><a class="cancel-link" href="produtos.php">Cancelar edição</a><?php endif; ?>
    </form>
  </div>

  <div class="panel inventory">
    <div class="section-heading"><div><span class="step">CARDÁPIO</span><h2>Produtos cadastrados</h2></div><span class="count"><?= count($produtos) ?> itens</span></div>
    <?php if (!$produtos): ?>
      <div class="empty">Nenhum produto cadastrado.</div>
    <?php else: ?>
      <div class="table-wrap"><table>
        <thead><tr><th>Produto</th><th>Categoria</th><th>Preço</th><th>Estoque</th><th>Status</th><th>Ações</th></tr></thead>
        <tbody>
        <?php foreach ($produtos as $p): ?>
          <tr>
            <td><strong><?= e($p['nome']) ?></strong><?php if ($p['descricao']): ?><small class="desc-cell"><?= e($p['descricao']) ?></small><?php endif; ?></td>
            <td><?= e($p['categoria']) ?></td>
            <td class="price-cell"><?= brl($p['preco']) ?></td>
            <td><span class="status <?= (int)$p['estoque'] === 0 ? 'status-off' : ((int)$p['estoque'] <= 5 ? 'status-low' : 'status-done') ?>"><?= (int)$p['estoque'] ?> un.</span></td>
            <td><span class="status <?= $p['ativo'] ? 'status-done' : 'status-off' ?>"><?= $p['ativo'] ? 'Ativo' : 'Inativo' ?></span></td>
            <td><div class="table-actions">
              <a class="small-link" href="produtos.php?editar=<?= (int)$p['id'] ?>">Editar</a>
              <form method="post" onsubmit="return confirm('Alterar disponibilidade deste produto?')">
                <?= csrf_campo() ?><input type="hidden" name="acao" value="alternar"><input type="hidden" name="id" value="<?= (int)$p['id'] ?>">
                <button class="text-button" type="submit"><?= $p['ativo'] ? 'Desativar' : 'Ativar' ?></button>
              </form>
            </div></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table></div>
    <?php endif; ?>
  </div>
</section>
<?php require __DIR__ . '/includes/rodape.php'; ?>
