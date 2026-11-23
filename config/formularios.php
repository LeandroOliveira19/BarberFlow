<?php
declare(strict_types=1);

/**
 * Validação dos formulários de Definições e de Perfil da gestão (funções puras, testadas em web_rotas.php).
 * Cada função devolve ['erro' => ?string, ...valores já limpos]. A primeira regra que falhar dá a mensagem.
 */
if (count(get_included_files()) === 1) { header('Location: ../index.php'); exit; }

const FONTES_TITULO   = ['Cinzel', 'Playfair Display', 'Montserrat', 'Outfit'];
const LAYOUTS_SITE    = ['classico', 'minimalista'];
const INTERVALOS_MARC = [15, 20, 30, 45, 60];

/** Texto com tamanho entre $min e $max (em caracteres). */
function textoValido(string $v, int $min, int $max): bool {
    $n = mb_strlen($v);
    return $n >= $min && $n <= $max;
}

/** Vazio ou ligação http(s):// até 500 caracteres. */
function ligacaoValida(string $v): bool {
    return $v === '' || (strlen($v) <= 500 && preg_match('#^https?://[^\s<>"]+$#i', $v) === 1 && filter_var($v, FILTER_VALIDATE_URL) !== false);
}

/** Telefone: tira espaços, pontos e hífenes; aceita um "+" inicial e 9 a 15 dígitos. Devolve null se for inválido. */
function telefoneLimpo(string $v): ?string {
    $v = preg_replace('/[\s.\-()]/', '', $v) ?? '';
    if ($v === '') return '';
    return preg_match('/^\+?\d{9,15}$/', $v) === 1 ? $v : null;
}

/** @return array{erro: ?string, nome: string, morada: string, configs: array<string,string>} */
/** Imagem enviada para a pasta da própria barbearia: uploads/barbearias/<id>/<nome>.jpg|png|webp */
function imagemDaBarbearia(string $v, int $bid): bool {
    return $bid > 0 && preg_match('#^uploads/barbearias/' . $bid . '/[A-Za-z0-9_-]+\.(jpg|png|webp)$#', $v) === 1;
}

/** $bid: barbearia da sessão; as imagens do site podem ser ligações http(s) ou ficheiros enviados para a pasta dela. */
function validarDefinicoes(array $post, int $bid = 0): array {
    $t = fn(string $k): string => trim((string)($post[$k] ?? ''));
    $nome = $t('nome');
    $morada = $t('morada');
    $res = ['erro' => null, 'nome' => $nome, 'morada' => $morada, 'configs' => []];
    $falha = function (string $msg) use (&$res): array { $res['erro'] = $msg; return $res; };

    if (!textoValido($nome, 1, 120))   return $falha('O nome da barbearia é obrigatório (até 120 caracteres).');
    if (!textoValido($morada, 1, 200)) return $falha('A morada é obrigatória (até 200 caracteres).');

    $tel = telefoneLimpo($t('telemovel_contacto'));
    if ($tel === null) return $falha('O telefone tem de ter entre 9 e 15 dígitos.');
    $email = $t('email_contacto');
    if ($email !== '' && (strlen($email) > 150 || !filter_var($email, FILTER_VALIDATE_EMAIL))) return $falha('O email de contacto não é válido.');

    $dias = [];
    foreach ((array)($post['dias'] ?? []) as $d) {
        $d = (string)$d;
        if (!ctype_digit($d) || (int)$d < 1 || (int)$d > 7) return $falha('Os dias de funcionamento não são válidos.');
        $dias[(int)$d] = (int)$d;
    }
    if (!$dias) return $falha('Escolhe pelo menos um dia de funcionamento.');
    ksort($dias);

    $abre = $t('hora_abertura');
    $fecha = $t('hora_fecho');
    $hora = '/^([01]\d|2[0-3]):[0-5]\d$/';
    if (!preg_match($hora, $abre) || !preg_match($hora, $fecha)) return $falha('As horas têm de estar no formato HH:MM.');
    if ($fecha <= $abre) return $falha('A hora de fecho tem de ser depois da hora de abertura.');

    $intervalo = $t('intervalo_marcacoes');
    if (!ctype_digit($intervalo) || !in_array((int)$intervalo, INTERVALOS_MARC, true)) return $falha('O intervalo entre marcações não é válido.');
    $antecedencia = $t('antecedencia_alteracao_horas');
    if (!ctype_digit($antecedencia) || (int)$antecedencia > 168) return $falha('A antecedência tem de ser um número de horas entre 0 e 168.');

    $slogan = $t('slogan');
    $sobre = $t('sobre');
    if (!textoValido($slogan, 0, 150)) return $falha('O slogan pode ter no máximo 150 caracteres.');
    if (!textoValido($sobre, 0, 2000)) return $falha('O texto "Sobre nós" pode ter no máximo 2000 caracteres.');

    $layout = $t('tema_layout');
    if (!in_array($layout, LAYOUTS_SITE, true)) return $falha('O layout escolhido não existe.');
    $fonte = $t('tema_fonte');
    if (!in_array($fonte, FONTES_TITULO, true)) return $falha('A fonte escolhida não existe.');
    $cor = strtolower($t('tema_cor'));
    $fundo = strtolower($t('tema_fundo'));
    if (!preg_match('/^#[0-9a-f]{6}$/', $cor) || !preg_match('/^#[0-9a-f]{6}$/', $fundo)) return $falha('As cores têm de estar no formato #rrggbb.');

    $ligacoes = ['tema_hero' => 'imagem principal', 'link_instagram' => 'Instagram', 'link_facebook' => 'Facebook',
                 'galeria_img_1' => 'imagem 1 da galeria', 'galeria_img_2' => 'imagem 2 da galeria', 'galeria_img_3' => 'imagem 3 da galeria'];
    $imagens = ['tema_hero', 'galeria_img_1', 'galeria_img_2', 'galeria_img_3'];
    foreach ($ligacoes as $k => $rotulo) {
        $v = $t($k);
        if (in_array($k, $imagens, true) && imagemDaBarbearia($v, $bid)) continue;
        if (!ligacaoValida($v)) return $falha("A ligação da $rotulo tem de começar por http:// ou https://.");
    }

    $res['configs'] = [
        'telemovel_contacto' => $tel,
        'email_contacto' => $email,
        'dias_funcionamento' => implode(',', $dias),
        'hora_abertura' => $abre,
        'hora_fecho' => $fecha,
        'intervalo_marcacoes' => (string)(int)$intervalo,
        'antecedencia_alteracao_horas' => (string)(int)$antecedencia,
        'slogan' => $slogan,
        'sobre' => $sobre,
        'tema_layout' => $layout,
        'tema_fonte' => $fonte,
        'tema_cor' => $cor,
        'tema_fundo' => $fundo,
    ];
    foreach (array_keys($ligacoes) as $k) $res['configs'][$k] = $t($k);
    return $res;
}

/**
 * Dados do perfil. O gestor não aparece no site, por isso só o barbeiro tem especialidades, biografia e Instagram.
 * @return array{erro: ?string, dados: array<string,?string>}
 */
function validarPerfil(array $post, bool $eBarbeiro): array {
    $t = fn(string $k): string => trim((string)($post[$k] ?? ''));
    $falha = fn(string $msg): array => ['erro' => $msg, 'dados' => []];

    $nome = $t('nome');
    if (!textoValido($nome, 2, 120)) return $falha('O nome tem de ter entre 2 e 120 caracteres.');
    $tel = telefoneLimpo($t('telefone'));
    if ($tel === null) return $falha('O telefone tem de ter entre 9 e 15 dígitos.');
    $foto = $t('foto_url');
    if ($foto !== '' && !ligacaoValida($foto) && preg_match('#^uploads/barbeiros/[A-Za-z0-9_-]+\.(jpg|jpeg|png|webp)$#', $foto) !== 1) {
        return $falha('A fotografia tem de ser uma ligação http(s):// ou um ficheiro enviado.');
    }
    $dados = ['nome' => $nome, 'telefone' => $tel === '' ? null : $tel, 'foto_url' => $foto === '' ? null : $foto];

    if ($eBarbeiro) {
        $esp = $t('especialidades');
        $bio = $t('bio');
        $insta = $t('instagram');
        if (!textoValido($esp, 0, 150)) return $falha('As especialidades podem ter no máximo 150 caracteres.');
        if (!textoValido($bio, 0, 1000)) return $falha('A biografia pode ter no máximo 1000 caracteres.');
        if (!ligacaoValida($insta)) return $falha('A ligação do Instagram tem de começar por http:// ou https://.');
        $dados += ['especialidades' => $esp, 'bio' => $bio, 'instagram' => $insta];
    }
    return ['erro' => null, 'dados' => $dados];
}
