<?php
// 1. Inclui a conexão
include 'conexao.php';

// Configuração: Quantos alunos deseja criar?
$qtd_alunos = 100;

// 2. Arrays de dados para sortear (NOVOS DADOS)
$nomes = [
    'Arthur', 'Beatriz', 'Caio', 'Diana', 'Eduardo', 'Fernanda', 'Gustavo', 'Heloisa', 
    'Igor', 'Juliana', 'Kaique', 'Larissa', 'Miguel', 'Natália', 'Otávio', 'Priscila', 
    'Renan', 'Sophia', 'Thiago', 'Vanessa', 'William', 'Yasmin', 'Yuri', 'Zélia'
];

$sobrenomes = [
    'Almeida', 'Barbosa', 'Campos', 'Dias', 'Duarte', 'Freitas', 'Gonçalves', 'Lopes', 
    'Machado', 'Mendes', 'Moreira', 'Moura', 'Nascimento', 'Nunes', 'Ramos', 'Ribeiro', 
    'Rocha', 'Santana', 'Vieira', 'Cardoso', 'Pinto', 'Batista', 'Viana', 'Farias'
];

// Cidades variadas (Foco Cariri/Norte/Centro-Sul para diferenciar das anteriores)
$cidades = [
    'Juazeiro do Norte', 'Crato', 'Barbalha', 'Sobral', 'Iguatu', 
    'Quixadá', 'Canindé', 'Russas', 'Aracati', 'Limoeiro do Norte'
];

// Bairros genéricos e comuns nessas novas cidades
$bairros = [
    'Lagoa Seca', 'Pirajá', 'Triângulo', 'Salesianos', 'Pimenta', 'Alto da Penha', 
    'São Miguel', 'Aeroporto', 'Campo dos Velhos', 'Pedrinhas', 'Jurema', 
    'Planalto Horizonte', 'Vila Alta', 'Santo Antônio'
];

$cursos = ['Informática', 'Desenvolvimento de Sistemas', 'Enfermagem', 'Administração'];
$tipos_resp = ['Pai', 'Mãe', 'Avó', 'Avô', 'Tio', 'Tia', 'Madrasta'];
$ruas_pref = ['Rua', 'Avenida', 'Alameda', 'Travessa', 'Beco'];

echo "<h3>Iniciando a geração de $qtd_alunos alunos com novos dados...</h3>";

// Prepara a query (fora do loop para ser mais rápido e seguro)
$sql = "INSERT INTO alunos (nome, data_nascimento, cidade, rua, bairro, numero, cep, nome_responsavel, tipo_responsavel, curso) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
$stmt = $conexao->prepare($sql);

if ($stmt === false) {
    die("Erro no prepare: " . $conexao->error);
}

// 3. Loop para criar os registros
for ($i = 0; $i < $qtd_alunos; $i++) {
    
    // Sorteia dados aleatórios
    // Gera Nome + Sobrenome + Sobrenome
    $nome_completo = $nomes[array_rand($nomes)] . ' ' . $sobrenomes[array_rand($sobrenomes)] . ' ' . $sobrenomes[array_rand($sobrenomes)];
    
    // Gera data de nascimento aleatória (entre 2000 e 2010)
    $timestamp = mt_rand(strtotime('2000-01-01'), strtotime('2010-12-31'));
    $data_nascimento = date('Y-m-d', $timestamp);
    
    $cidade = $cidades[array_rand($cidades)];
    // Rua agora pega um sobrenome aleatório para parecer nome de rua (ex: Rua Almeida)
    $rua = $ruas_pref[array_rand($ruas_pref)] . ' ' . $sobrenomes[array_rand($sobrenomes)];
    $bairro = $bairros[array_rand($bairros)];
    $numero = rand(10, 999); 
    $cep = rand(60000, 63900) . '-' . rand(100, 999); // Faixa de CEP mais coerente com interior do CE
    
    $nome_responsavel = $nomes[array_rand($nomes)] . ' ' . $sobrenomes[array_rand($sobrenomes)];
    $tipo_responsavel = $tipos_resp[array_rand($tipos_resp)];
    $curso = $cursos[array_rand($cursos)];

    // Vincula os parâmetros e executa
    $stmt->bind_param("ssssssssss", 
        $nome_completo, 
        $data_nascimento, 
        $cidade, 
        $rua, 
        $bairro, 
        $numero, 
        $cep, 
        $nome_responsavel, 
        $tipo_responsavel, 
        $curso
    );
    
    $stmt->execute();
}

echo "✅ <strong>Sucesso!</strong> $qtd_alunos novos alunos foram inseridos no banco de dados.<br>";
echo "<br><a href='listar_alunos.php'>Ver Lista de Alunos</a>";

// Fecha conexão
$stmt->close();
$conexao->close();
?>