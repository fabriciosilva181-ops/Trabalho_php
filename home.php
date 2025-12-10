<?php
include 'conexao.php';

// --- 1. LÓGICA DE FILTROS ---
$filtro_curso = $_GET['curso'] ?? '';
$filtro_cidade = $_GET['cidade'] ?? '';
$filtro_tipo = $_GET['tipo_responsavel'] ?? '';
$filtro_idade_min = $_GET['idade_min'] ?? '';
$filtro_idade_max = $_GET['idade_max'] ?? '';

$where = "WHERE 1=1";

if (!empty($filtro_curso)) {
    $where .= " AND curso = '$filtro_curso'";
}
if (!empty($filtro_cidade)) {
    $where .= " AND cidade = '$filtro_cidade'";
}
if (!empty($filtro_tipo)) {
    $where .= " AND tipo_responsavel = '$filtro_tipo'";
}
if (!empty($filtro_idade_min)) {
    $where .= " AND TIMESTAMPDIFF(YEAR, data_nascimento, CURDATE()) >= $filtro_idade_min";
}
if (!empty($filtro_idade_max)) {
    $where .= " AND TIMESTAMPDIFF(YEAR, data_nascimento, CURDATE()) <= $filtro_idade_max";
}

// --- 2. CONSULTAS PARA OS CARDS SUPERIORES (KPIs) ---
$sqlKPI = "SELECT 
    COUNT(*) as total_geral,
    SUM(CASE WHEN curso = 'Enfermagem' THEN 1 ELSE 0 END) as total_enf,
    SUM(CASE WHEN curso = 'Informática' THEN 1 ELSE 0 END) as total_inf,
    SUM(CASE WHEN curso = 'Desenvolvimento de Sistemas' THEN 1 ELSE 0 END) as total_ds,
    SUM(CASE WHEN curso = 'Administração' THEN 1 ELSE 0 END) as total_adm
 FROM alunos $where";
$kpi = $conexao->query($sqlKPI)->fetch_assoc();
if (!$kpi) $kpi = ['total_geral'=>0, 'total_enf'=>0, 'total_inf'=>0, 'total_ds'=>0, 'total_adm'=>0];

// --- 3. DADOS PARA GRÁFICOS E PREMIAÇÃO ---

// A) Dados por Curso (Pizza)
$sqlCurso = "SELECT curso, COUNT(*) as qtd FROM alunos $where GROUP BY curso ORDER BY qtd DESC";
$resCurso = $conexao->query($sqlCurso);
$cursosLabel = [];
$cursosData = [];
$cursoVencedor = "Nenhum";
$cursoVencedorQtd = 0;

while($row = $resCurso->fetch_assoc()) {
    $cursosLabel[] = $row['curso'];
    $cursosData[] = $row['qtd'];
    if ($row['qtd'] > $cursoVencedorQtd) {
        $cursoVencedorQtd = $row['qtd'];
        $cursoVencedor = $row['curso'];
    }
}

// B) Dados por Cidade (Barra Vertical)
$sqlCidade = "SELECT cidade, COUNT(*) as qtd FROM alunos $where GROUP BY cidade ORDER BY qtd DESC";
$resCidade = $conexao->query($sqlCidade);
$cidadesLabel = [];
$cidadesData = [];
$cidadeVencedora = "Nenhuma";
$cidadeVencedoraQtd = 0;

while($row = $resCidade->fetch_assoc()) {
    $cidadesLabel[] = $row['cidade'];
    $cidadesData[] = $row['qtd'];
    if ($row['qtd'] > $cidadeVencedoraQtd) {
        $cidadeVencedoraQtd = $row['qtd'];
        $cidadeVencedora = $row['cidade'];
    }
}

// C) ALTERADO: Dados por Tipo de Responsável (Substituindo Idade)
// Agora buscamos a contagem baseada no tipo de responsável
$sqlTipoResp = "SELECT tipo_responsavel, COUNT(*) as qtd 
              FROM alunos $where 
              GROUP BY tipo_responsavel 
              ORDER BY qtd DESC";
$resTipoResp = $conexao->query($sqlTipoResp);
$tiposLabel = [];
$tiposData = [];

while($row = $resTipoResp->fetch_assoc()) {
    // Caso o campo esteja vazio, colocamos "Não Informado" para o gráfico não quebrar
    $label = empty($row['tipo_responsavel']) ? 'Não Informado' : $row['tipo_responsavel'];
    $tiposLabel[] = $label;
    $tiposData[] = $row['qtd'];
}

// --- 4. CARREGAR OPÇÕES DOS FILTROS (SELECTS) ---
$optCidades = $conexao->query("SELECT DISTINCT cidade FROM alunos ORDER BY cidade");
$optTipos = $conexao->query("SELECT DISTINCT tipo_responsavel FROM alunos ORDER BY tipo_responsavel");

?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.0.0-beta2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Open+Sans:wght@400;600;700&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

    <title>Dashboard Power BI Style</title>

    <style>
        /* Aplicando a nova fonte Open Sans */
        body { background-color: #f0f2f5; font-family: 'Open Sans', sans-serif; }
        
        /* Estilos dos Cards Superiores (Mantidos iguais) */
        .card-kpi { border: none; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); transition: transform 0.2s;}
        .card-kpi:hover { transform: translateY(-5px); }
        .kpi-header { color: white; font-weight: bold; text-align: center; border-radius: 8px 8px 0 0; padding: 10px;}
        .kpi-body { text-align: center; padding: 20px; background: white; border-radius: 0 0 8px 8px; }
        .kpi-number { font-size: 2rem; font-weight: 800; color: #333; }
        
        /* Cores KPI (Mantidas) */
        .bg-enf { background-color: #074f18ff; }
        .bg-inf { background-color: #9668ebff; }
        .bg-ds { background-color: #ded410ff; }
        .bg-adm { background-color: #92bae5ff; }

        /* Estilos dos Cards de Filtro */
        .filter-header { background-color: #6c757d; color: white; font-weight: bold; text-align: center; border-radius: 5px 5px 0 0; padding: 5px; }
        .card-filter { border: none; box-shadow: 0 2px 5px rgba(0,0,0,0.05); }

        /* Estilos dos Gráficos */
        .chart-card { border: none; box-shadow: 0 4px 15px rgba(0,0,0,0.1); margin-bottom: 20px; border-radius: 10px; background: white;}
        .chart-header { padding: 10px 15px; border-radius: 10px 10px 0 0; font-weight: bold; color: white; font-size: 1.1rem; }
        
        /* Cores Gráficos - Headers mantidos para integridade do layout, cores internas dos gráficos alteradas via JS */
        .header-pizza { background: linear-gradient(45deg, #1e3c72, #2a5298); }
        .header-bar-vert { background: linear-gradient(45deg, #11998e, #38ef7d); }
        .header-bar-horiz { background: linear-gradient(45deg, #c009e9ff, #230892ff); }

        /* Estilos da Premiação (Mantidos) */
        .award-card { border: 2px solid #DAA520; border-radius: 15px; overflow: hidden; box-shadow: 0 4px 20px rgba(218, 165, 32, 0.3); }
        .award-header { background-color: #DAA520; color: white; text-align: center; padding: 15px; font-size: 1.3rem; font-weight: bold; text-transform: uppercase; letter-spacing: 1px;}
        .award-body { background: #fff; padding: 30px; text-align: center; }
        .trophy-icon { font-size: 3rem; margin-bottom: 10px; display: block; }
        .winner-name { font-size: 1.8rem; font-weight: bold; color: #333; }
        .winner-count { color: #777; font-size: 1rem; }

    </style>
</head>
<body>

<?php include('navbar.php'); ?>

<div class="container-fluid p-4">

    <div class="row row-cols-1 row-cols-md-5 g-3 mb-4">
        <div class="col">
            <div class="card-kpi">
                <div class="kpi-header bg-primary">Total Alunos</div>
                <div class="kpi-body"><span class="kpi-number"><?php echo $kpi['total_geral']; ?></span></div>
            </div>
        </div>
        <div class="col">
            <div class="card-kpi">
                <div class="kpi-header bg-enf">Enfermagem</div>
                <div class="kpi-body"><span class="kpi-number"><?php echo $kpi['total_enf']; ?></span></div>
            </div>
        </div>
        <div class="col">
            <div class="card-kpi">
                <div class="kpi-header bg-inf">Informática</div>
                <div class="kpi-body"><span class="kpi-number"><?php echo $kpi['total_inf']; ?></span></div>
            </div>
        </div>
        <div class="col">
            <div class="card-kpi">
                <div class="kpi-header bg-ds">Desenv. Sist.</div>
                <div class="kpi-body"><span class="kpi-number"><?php echo $kpi['total_ds']; ?></span></div>
            </div>
        </div>
        <div class="col">
            <div class="card-kpi">
                <div class="kpi-header bg-adm">Administração</div>
                <div class="kpi-body"><span class="kpi-number"><?php echo $kpi['total_adm']; ?></span></div>
            </div>
        </div>
    </div>

    <form method="GET" action="home.php">
        <div class="row g-3 mb-5">
            <div class="col-md-3">
                <div class="card card-filter">
                    <div class="filter-header">Filtrar Curso</div>
                    <div class="p-2">
                        <select class="form-select" name="curso" onchange="this.form.submit()">
                            <option value="">Todos os Cursos</option>
                            <option value="Informática" <?= $filtro_curso=='Informática'?'selected':'' ?>>Informática</option>
                            <option value="Desenvolvimento de Sistemas" <?= $filtro_curso=='Desenvolvimento de Sistemas'?'selected':'' ?>>Desenv. Sistemas</option>
                            <option value="Enfermagem" <?= $filtro_curso=='Enfermagem'?'selected':'' ?>>Enfermagem</option>
                            <option value="Administração" <?= $filtro_curso=='Administração'?'selected':'' ?>>Administração</option>
                        </select>
                    </div>
                </div>
            </div>

            <div class="col-md-3">
                <div class="card card-filter">
                    <div class="filter-header">Filtrar Cidade</div>
                    <div class="p-2">
                        <select class="form-select" name="cidade" onchange="this.form.submit()">
                            <option value="">Todas as Cidades</option>
                            <?php while($c = $optCidades->fetch_assoc()): ?>
                                <option value="<?php echo $c['cidade']; ?>" <?= $filtro_cidade==$c['cidade']?'selected':'' ?>>
                                    <?php echo $c['cidade']; ?>
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                </div>
            </div>

            <div class="col-md-3">
                <div class="card card-filter">
                    <div class="filter-header">Tipo Responsável</div>
                    <div class="p-2">
                        <select class="form-select" name="tipo_responsavel" onchange="this.form.submit()">
                            <option value="">Todos</option>
                            <?php while($t = $optTipos->fetch_assoc()): ?>
                                <option value="<?php echo $t['tipo_responsavel']; ?>" <?= $filtro_tipo==$t['tipo_responsavel']?'selected':'' ?>>
                                    <?php echo $t['tipo_responsavel']; ?>
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                </div>
            </div>

            <div class="col-md-3">
                <div class="card card-filter">
                    <div class="filter-header">Idade (Min - Max)</div>
                    <div class="p-2 d-flex gap-1">
                        <input type="number" class="form-control" name="idade_min" placeholder="Min" value="<?= $filtro_idade_min ?>">
                        <input type="number" class="form-control" name="idade_max" placeholder="Max" value="<?= $filtro_idade_max ?>">
                        <button type="submit" class="btn btn-secondary btn-sm">OK</button>
                    </div>
                </div>
            </div>
            
            <div class="col-12 text-end">
                <a href="home.php" class="btn btn-outline-secondary btn-sm">Limpar Filtros</a>
            </div>
        </div>
    </form>

    <div class="row">
        <div class="col-md-5">
            <div class="chart-card h-100">
                <div class="chart-header header-pizza">Distribuição por Curso</div>
                <div class="card-body">
                    <canvas id="chartCurso"></canvas>
                </div>
            </div>
        </div>

        <div class="col-md-7">
            <div class="chart-card h-100">
                <div class="chart-header header-bar-vert">Alunos por Cidade</div>
                <div class="card-body">
                    <canvas id="chartCidade"></canvas>
                </div>
            </div>
        </div>
    </div>

    <div class="row mt-4">
        <div class="col-12">
            <div class="chart-card">
                <div class="chart-header header-bar-horiz">Qtd. de Alunos por Tipo de Responsável</div>
                <div class="card-body">
                    <div style="height: 300px;">
                        <canvas id="chartTipoResponsavel"></canvas>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row justify-content-center mt-5 mb-5">
        <div class="col-md-5 mb-3">
            <div class="award-card">
                <div class="award-header">🏆 Curso Mais Procurado</div>
                <div class="award-body">
                    <span class="trophy-icon">🥇</span>
                    <div class="winner-name"><?php echo $cursoVencedor; ?></div>
                    <div class="winner-count"><?php echo $cursoVencedorQtd; ?> alunos inscritos</div>
                </div>
            </div>
        </div>

        <div class="col-md-5 mb-3">
            <div class="award-card">
                <div class="award-header">🏙️ Cidade Destaque</div>
                <div class="award-body">
                    <span class="trophy-icon">🏅</span>
                    <div class="winner-name"><?php echo $cidadeVencedora; ?></div>
                    <div class="winner-count"><?php echo $cidadeVencedoraQtd; ?> alunos residentes</div>
                </div>
            </div>
        </div>
    </div>

</div>

<?php include('footer.php'); ?>

<script>
    // --- Configuração Global de Fontes ---
    // Alterado para Open Sans
    Chart.defaults.color = '#333';
    Chart.defaults.font.family = "'Open Sans', sans-serif";
    Chart.defaults.font.weight = '600';

    // 1. Gráfico de Pizza (Cursos)
    // CORES ALTERADAS: Paleta "Cool"
    const ctxCurso = document.getElementById('chartCurso').getContext('2d');
    new Chart(ctxCurso, {
        type: 'pie',
        data: {
            labels: <?php echo json_encode($cursosLabel); ?>,
            datasets: [{
                data: <?php echo json_encode($cursosData); ?>,
                // Novas cores (Roxos, Azuis e Rosas) em vez das cores padrão bootstrap
                backgroundColor: [
                    '#4e73df', // Azul forte
                    '#36b9cc', // Ciano
                    '#1cc88a', // Verde Esmeralda
                    '#f6c23e', // Amarelo Queimado
                    '#e74a3b', // Vermelho Suave
                    '#858796'  // Cinza
                ],
                borderWidth: 2,
                borderColor: '#ffffff'
            }]
        },
        options: {
            responsive: true,
            plugins: {
                legend: { position: 'right' } // Mudei a legenda para o lado para variar
            }
        }
    });

    // 2. Gráfico de Barras Verticais (Cidades)
    // CORES ALTERADAS: Roxo Profundo
    const ctxCidade = document.getElementById('chartCidade').getContext('2d');
    new Chart(ctxCidade, {
        type: 'bar',
        data: {
            labels: <?php echo json_encode($cidadesLabel); ?>,
            datasets: [{
                label: 'Quantidade de Alunos',
                data: <?php echo json_encode($cidadesData); ?>,
                backgroundColor: '#6610f2', // Roxo Bootstrap (Indigo)
                borderColor: '#520dc2',
                borderWidth: 1,
                borderRadius: 5 // Barras arredondadas
            }]
        },
        options: {
            responsive: true,
            scales: {
                y: { beginAtZero: true, grid: { color: '#f8f9fa' } },
                x: { grid: { display: false } }
            },
            plugins: { legend: { display: false } }
        }
    });

    // 3. NOVO GRÁFICO: Tipos de Responsável (Substituiu Idade)
    // CORES ALTERADAS: Laranja/Coral
    const ctxTipo = document.getElementById('chartTipoResponsavel').getContext('2d');
    new Chart(ctxTipo, {
        type: 'bar', // Mantive barra
        data: {
            labels: <?php echo json_encode($tiposLabel); ?>,
            datasets: [{
                label: 'Responsáveis',
                data: <?php echo json_encode($tiposData); ?>,
                backgroundColor: 'rgba(253, 126, 20, 0.8)', // Laranja
                borderColor: '#fd7e14',
                borderWidth: 1,
                barPercentage: 0.6 // Barras mais finas
            }]
        },
        options: {
            indexAxis: 'y', // Barra Horizontal
            responsive: true,
            maintainAspectRatio: false,
            scales: {
                x: { beginAtZero: true, grid: { color: '#f8f9fa' } }
            },
            plugins: { legend: { display: false } }
        }
    });
</script>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.0.0-beta2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>