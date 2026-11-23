<?php
declare(strict_types=1);
// Módulo: definicoes / guardar — (POST) O CSRF e o perfil já foram validados pelo gestao/index.php.
// As regras de cada campo estão em config/formularios.php (validarDefinicoes).
// A aparência do site (layout, fonte, cores) não se muda aqui: é configurada pelo administrador do BarberFlow.
if (count(get_included_files()) === 1) { header('Location: ../../index.php'); exit; }

if ($_SERVER['REQUEST_METHOD'] !== 'POST') redirecionar(urlModulo('definicoes', 'editar'));

require_once __DIR__ . '/../../../config/formularios.php';
require_once __DIR__ . '/../../../config/uploads.php';

const IMAGENS_SITE = ['tema_hero' => 'hero', 'galeria_img_1' => 'galeria1', 'galeria_img_2' => 'galeria2', 'galeria_img_3' => 'galeria3'];
$raiz       = dirname(__DIR__, 3);
$pastaRel   = "uploads/barbearias/$bid/";

$definicoes = new Configuracao($pdo, $bid);   // objects/Configuracao.php
$guardadas  = $definicoes->read();

// Envio parcial: o que não vier no pedido mantém o valor atual.
// Os dias são interruptores (um checkbox desligado não é enviado): só contam quando o formulário traz "dias_enviados".
$atuais = $guardadas + $definicoes->dadosBarbearia() + [
    'intervalo_marcacoes' => '15', 'antecedencia_alteracao_horas' => '24', 'hora_abertura' => '09:00', 'hora_fecho' => '19:00',
];
$atuais['dias'] = explode(',', (string)($atuais['dias_funcionamento'] ?? '1,2,3,4,5,6'));
$pedido = $_POST;
if (!empty($pedido['dias_enviados']) && !isset($pedido['dias'])) $pedido['dias'] = [];

// Aparência: o que vier no pedido é ignorado; valida-se com valores fixos e não se grava
$aparencia = ['tema_layout' => 'classico', 'tema_fonte' => 'Cinzel', 'tema_cor' => '#cda47c', 'tema_fundo' => '#0a0a0a'];
$pedido = array_merge($pedido, $aparencia);

// Contactos que não vêm no pedido não são validados nem regravados (um valor antigo inválido não bloqueia o resto)
$naoGravar = array_keys($aparencia);
foreach (['telemovel_contacto', 'email_contacto'] as $k) {
    if (!array_key_exists($k, $pedido)) { $atuais[$k] = ''; $naoGravar[] = $k; }
}

// Imagens do site: remover, ficheiro do dispositivo, ligação escrita, ou manter a que já lá está
$remover   = array_map('strval', (array)($_POST['remover'] ?? []));
$enviadas  = [];   // ficheiros gravados neste pedido (para apagar se a validação falhar)
$aApagar   = [];   // ficheiros antigos substituídos ou removidos (apagados só depois de gravar)
foreach (IMAGENS_SITE as $campo => $prefixo) {
    $antiga = (string)($guardadas[$campo] ?? '');
    $antigaEnviada = imagemDaBarbearia($antiga, $bid);
    $ficheiro = $_FILES[$campo . '_ficheiro'] ?? null;

    if (in_array($campo, $remover, true)) {
        $pedido[$campo] = '';
    } elseif (imagemFoiEnviada($ficheiro)) {
        $r = guardarImagemEnviada($ficheiro, "$raiz/$pastaRel", $prefixo);
        if ($r['erro'] !== null) {
            foreach ($enviadas as $f) apagarImagemEnviada($f, $pastaRel, $raiz);
            flash('erro', $r['erro']);
            redirecionar(urlModulo('definicoes', 'editar'));
        }
        $pedido[$campo] = $pastaRel . $r['nome'];
        $enviadas[] = $pedido[$campo];
    } elseif (!array_key_exists($campo, $pedido) || (trim((string)$pedido[$campo]) === '' && $antigaEnviada)) {
        $pedido[$campo] = $antiga;   // campo da ligação vazio e havia um ficheiro enviado: mantém-se
    }
    if ($antigaEnviada && $pedido[$campo] !== $antiga) $aApagar[] = $antiga;
}

$valido = validarDefinicoes($pedido + $atuais, $bid);
if ($valido['erro'] !== null) {
    foreach ($enviadas as $f) apagarImagemEnviada($f, $pastaRel, $raiz);
    flash('erro', $valido['erro']);
    redirecionar(urlModulo('definicoes', 'editar'));
}

$definicoes->atualizarBarbearia($valido['nome'], $valido['morada']);
$definicoes->save(array_diff_key($valido['configs'], array_flip($naoGravar)));
foreach ($aApagar as $f) apagarImagemEnviada($f, $pastaRel, $raiz);

flash('sucesso', 'Definições guardadas.');
redirecionar(urlModulo('definicoes', 'editar'));
