<?php
declare(strict_types=1);
// Módulo: definicoes / editar — incluído por gestao/index.php
// Listas agrupadas tipo iOS. A validação está em config/formularios.php (usada pelo guardar.php).
// A aparência do site (layout, fonte, cores) não aparece aqui: é configurada pelo administrador do BarberFlow.
if (count(get_included_files()) === 1) { header('Location: ../../index.php'); exit; }

require_once __DIR__ . '/../../../config/formularios.php';
require_once __DIR__ . '/../../../config/marcacoes.php';

$definicoes = new Configuracao($pdo, $bid);   // objects/Configuracao.php
$barbearia  = $definicoes->dadosBarbearia();
$config     = $definicoes->read();

/** Valor guardado (sem escape: quem o mostra usa e()). */
$cfg = fn(string $k, string $omissao = ''): string => (string)($config[$k] ?? $omissao);

$diasAtivos = diasFuncionamento($config);
$NOMES_DIAS = [1 => 'Seg', 2 => 'Ter', 3 => 'Qua', 4 => 'Qui', 5 => 'Sex', 6 => 'Sáb', 7 => 'Dom'];
$intervaloAtual = intervaloMinutos($config);
$IMAGENS = ['tema_hero' => 'Imagem principal', 'galeria_img_1' => 'Galeria — imagem 1', 'galeria_img_2' => 'Galeria — imagem 2', 'galeria_img_3' => 'Galeria — imagem 3'];
?>

<style>
    .definicoes { max-width: 760px; }
    .estado-maps { margin-left: auto; font-size: 0.85rem; }
    .estado-maps.ligado { color: #30d158; } .estado-maps.desligado { color: var(--muted); }

    /* Imagem do site: pré-visualização + ficheiro do dispositivo ou ligação */
    .imagem-linha { display: grid; grid-template-columns: 96px 1fr; gap: 0.6rem 1rem; align-items: center; }
    .imagem-linha .rotulo { grid-column: 1 / -1; width: auto; }
    .imagem-previa { width: 96px; height: 64px; border-radius: 12px; object-fit: cover; background: var(--superficie); border: 1px solid var(--linha); display: grid; place-items: center; color: var(--muted); font-size: 0.72rem; grid-row: span 2; }
    .imagem-opcoes { display: flex; flex-direction: column; gap: 0.5rem; min-width: 0; }
    .imagem-opcoes input[type="url"] { width: 100%; background: var(--fundo) !important; border: 1px solid rgba(255,255,255,0.12) !important; border-radius: var(--raio-campo); padding: 0.5rem 0.8rem !important; text-align: left !important; }
    .imagem-opcoes input[type="file"] { color: var(--muted); font-size: 0.82rem; max-width: 100%; }
    .imagem-opcoes input[type="file"]::file-selector-button { background: var(--superficie); color: var(--texto); border: 1px solid var(--linha); border-radius: 999px; padding: 0.35rem 0.9rem; margin-right: 0.6rem; cursor: pointer; font: inherit; font-size: 0.82rem; }
    .imagem-remover { display: inline-flex; align-items: center; gap: 0.5rem; color: var(--muted); font-size: 0.82rem; cursor: pointer; }
</style>

<div class="main-content definicoes">
    <div class="header">
        <div>
            <h1>Definições</h1>
            <p>Os dados da barbearia, o horário e o conteúdo do site público.</p>
        </div>
    </div>

    <form method="POST" action="<?= e(urlModulo('definicoes', 'guardar')) ?>" enctype="multipart/form-data">
        <?= campoCsrf() ?>

        <section class="grupo-form">
            <h2>Barbearia</h2>
            <p class="ajuda">Aparece no site, na app e nos emails enviados aos clientes.</p>
            <div class="lista">
                <div class="campo-linha"><label for="d-nome">Nome</label><input id="d-nome" type="text" name="nome" required maxlength="120" value="<?= e((string)$barbearia['nome']) ?>"></div>
                <div class="campo-linha"><label for="d-morada">Morada</label><input id="d-morada" type="text" name="morada" required maxlength="200" value="<?= e((string)$barbearia['morada']) ?>"></div>
                <div class="campo-linha"><label for="d-tel">Telefone</label><input id="d-tel" type="tel" name="telemovel_contacto" maxlength="20" placeholder="912 345 678" value="<?= e($cfg('telemovel_contacto')) ?>"></div>
                <div class="campo-linha"><label for="d-email">Email de contacto</label><input id="d-email" type="email" name="email_contacto" maxlength="150" placeholder="geral@barbearia.pt" value="<?= e($cfg('email_contacto')) ?>"></div>
            </div>
        </section>

        <section class="grupo-form">
            <h2>Horário e marcações</h2>
            <p class="ajuda">Os clientes só conseguem marcar nos dias e horas em que a barbearia está aberta.</p>
            <div class="lista">
                <div class="campo-linha">
                    <span class="rotulo">Dias abertos</span>
                    <input type="hidden" name="dias_enviados" value="1">
                    <div class="dias-linha" role="group" aria-label="Dias de funcionamento">
                        <?php foreach ($NOMES_DIAS as $n => $rotulo): ?>
                            <label class="dia">
                                <?= e($rotulo) ?>
                                <input class="interruptor" type="checkbox" name="dias[]" value="<?= $n ?>" <?= in_array($n, $diasAtivos, true) ? 'checked' : '' ?>>
                            </label>
                        <?php endforeach; ?>
                    </div>
                </div>
                <div class="campo-linha"><label for="d-abre">Abre às</label><input id="d-abre" type="time" name="hora_abertura" required value="<?= e($cfg('hora_abertura', '09:00')) ?>"></div>
                <div class="campo-linha"><label for="d-fecha">Fecha às</label><input id="d-fecha" type="time" name="hora_fecho" required value="<?= e($cfg('hora_fecho', '19:00')) ?>"></div>
                <div class="campo-linha">
                    <label for="d-intervalo">Intervalo entre marcações<small>De quanto em quanto tempo há horários livres</small></label>
                    <select id="d-intervalo" name="intervalo_marcacoes">
                        <?php foreach (INTERVALOS_MARC as $min): ?>
                            <option value="<?= $min ?>" <?= $intervaloAtual === $min ? 'selected' : '' ?>><?= $min ?> min</option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="campo-linha">
                    <label for="d-antec">Cancelar/remarcar na app até<small>Horas antes da marcação (0 = até à hora)</small></label>
                    <input id="d-antec" type="number" name="antecedencia_alteracao_horas" min="0" max="168" step="1" required value="<?= e($cfg('antecedencia_alteracao_horas', '24')) ?>">
                </div>
            </div>
        </section>

        <section class="grupo-form">
            <h2>Site público</h2>
            <p class="ajuda">As imagens podem vir do teu dispositivo (JPG, PNG ou WEBP, até 2 MB) ou de uma ligação da internet.</p>
            <div class="lista">
                <div class="campo-linha"><label for="d-slogan">Slogan</label><input id="d-slogan" type="text" name="slogan" maxlength="150" value="<?= e($cfg('slogan')) ?>"></div>
                <div class="campo-linha coluna"><label for="d-sobre">Sobre nós</label><textarea id="d-sobre" name="sobre" rows="4" maxlength="2000"><?= e($cfg('sobre')) ?></textarea></div>
                <?php foreach ($IMAGENS as $campo => $rotulo): ?>
                    <?php
                    $valor = $cfg($campo);
                    $enviada = imagemDaBarbearia($valor, $bid);
                    $previa = $valor === '' ? '' : ($enviada ? '../' . $valor : $valor);
                    ?>
                    <div class="campo-linha imagem-linha">
                        <span class="rotulo"><?= e($rotulo) ?><?= $enviada ? '<small>Fotografia enviada do dispositivo</small>' : '' ?></span>
                        <?php if ($previa !== ''): ?>
                            <img class="imagem-previa" src="<?= e($previa) ?>" alt="">
                        <?php else: ?>
                            <span class="imagem-previa">Sem imagem</span>
                        <?php endif; ?>
                        <div class="imagem-opcoes">
                            <input type="file" name="<?= e($campo) ?>_ficheiro" accept="image/jpeg, image/png, image/webp" aria-label="<?= e($rotulo) ?>: escolher do dispositivo">
                            <input type="url" name="<?= e($campo) ?>" maxlength="500" placeholder="ou cola uma ligação https://…" aria-label="<?= e($rotulo) ?>: ligação" value="<?= e($enviada ? '' : $valor) ?>">
                            <?php if ($valor !== ''): ?>
                                <label class="imagem-remover"><input class="interruptor" type="checkbox" name="remover[]" value="<?= e($campo) ?>"> Remover esta imagem</label>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
                <div class="campo-linha"><label for="d-insta">Instagram</label><input id="d-insta" type="url" name="link_instagram" maxlength="500" placeholder="https://instagram.com/…" value="<?= e($cfg('link_instagram')) ?>"></div>
                <div class="campo-linha"><label for="d-face">Facebook <small>Opcional</small></label><input id="d-face" type="url" name="link_facebook" maxlength="500" placeholder="https://facebook.com/…" value="<?= e($cfg('link_facebook')) ?>"></div>
                <div class="campo-linha">
                    <span class="rotulo">Avaliações do Google</span>
                    <?php if (!empty($config['google_place_id'])): ?>
                        <span class="estado-maps ligado">Ligadas</span>
                    <?php else: ?>
                        <span class="estado-maps desligado">Não configuradas</span>
                    <?php endif; ?>
                </div>
            </div>
        </section>

        <div class="barra-guardar"><button type="submit" class="btn-submit">Guardar alterações</button></div>
    </form>
</div>
