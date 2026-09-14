<?php
// api/mecklittle_engine.php — motor preditivo MeckLittle (heurísticas + NLP leve)

require_once __DIR__ . '/mecklittle_schema.php';

function ml_privacy_log(PDO $pdo, string $action, ?int $actor, ?int $subject, string $detail, bool $anon = false): void
{
    try {
        $stmt = $pdo->prepare("
            INSERT INTO mecklittle_privacy_log (action, actor_user_id, subject_user_id, detail, anonymized)
            VALUES (?, ?, ?, ?, ?)
        ");
        $stmt->execute([$action, $actor, $subject, $detail, $anon ? 1 : 0]);
    } catch (Throwable $e) { /* ignore */ }
}

function ml_analyze_sentiment(string $text): array
{
    $t = mb_strtolower($text, 'UTF-8');
    $pos = ['bom', 'ótimo', 'otimo', 'excelente', 'feliz', 'motivado', 'gosto', 'satisfeito', 'apoiado', 'crescer', 'obrigado'];
    $neg = ['mau', 'ruim', 'péssimo', 'pessimo', 'triste', 'cansado', 'stress', 'estresse', 'desmotivado', 'injusto', 'pressa', 'sobrecarga', 'assédio', 'assedio'];
    $stress = ['stress', 'estresse', 'ansiedade', 'burnout', 'esgotado', 'pressão', 'pressao', 'overload', 'não aguento', 'nao aguento'];

    $score = 0;
    $themes = [];
    foreach ($pos as $w) {
        if (mb_strpos($t, $w) !== false) { $score += 1; $themes[] = $w; }
    }
    foreach ($neg as $w) {
        if (mb_strpos($t, $w) !== false) { $score -= 1; $themes[] = $w; }
    }
    $isStress = false;
    foreach ($stress as $w) {
        if (mb_strpos($t, $w) !== false) { $isStress = true; $themes[] = $w; }
    }

    if ($isStress || $score <= -2) {
        $sentiment = 'stress';
    } elseif ($score >= 2) {
        $sentiment = 'positivo';
    } elseif ($score <= -1) {
        $sentiment = 'negativo';
    } else {
        $sentiment = 'neutro';
    }

    return [
        'sentiment' => $sentiment,
        'themes' => array_values(array_unique($themes)),
        'score_hint' => max(1, min(5, 3 + $score)),
    ];
}

function ml_tokenize_skills(string $text): array
{
    $map = [
        'php' => 'PHP', 'javascript' => 'JavaScript', 'js' => 'JavaScript', 'python' => 'Python',
        'sql' => 'SQL', 'mysql' => 'MySQL', 'react' => 'React', 'vue' => 'Vue',
        'laravel' => 'Laravel', 'docker' => 'Docker', 'git' => 'Git',
        'ágil' => 'Metodologias Ágeis', 'agil' => 'Metodologias Ágeis', 'scrum' => 'Scrum',
        'kanban' => 'Kanban', 'liderança' => 'Liderança', 'lideranca' => 'Liderança',
        'comunicação' => 'Comunicação', 'comunicacao' => 'Comunicação',
        'excel' => 'Excel', 'power bi' => 'Power BI', 'rh' => 'RH', 'recrutamento' => 'Recrutamento',
    ];
    $t = mb_strtolower($text, 'UTF-8');
    $found = [];
    foreach ($map as $needle => $label) {
        if (mb_strpos($t, $needle) !== false) {
            $found[$label] = true;
        }
    }
    return array_keys($found);
}

function ml_score_candidate(PDO $pdo, array $app, array $job): array
{
    $skillsText = ($app['skills_description'] ?? '') . ' ' . ($app['interview_notes'] ?? '');
    $reqText = ($job['requirements'] ?? '') . ' ' . ($job['description'] ?? '');
    $candSkills = ml_tokenize_skills($skillsText);
    $reqSkills = ml_tokenize_skills($reqText);
    if (!$reqSkills) {
        $reqSkills = ['PHP', 'SQL', 'Comunicação', 'Metodologias Ágeis'];
    }

    $match = 0;
    foreach ($reqSkills as $s) {
        if (in_array($s, $candSkills, true)) $match++;
    }
    $technical = $reqSkills ? round(($match / count($reqSkills)) * 100, 1) : 50;

    // Cultural fit: tom das notas + completude do perfil
    $sent = ml_analyze_sentiment($skillsText ?: 'neutro');
    $cultural = 55;
    if ($sent['sentiment'] === 'positivo') $cultural += 20;
    if ($sent['sentiment'] === 'negativo' || $sent['sentiment'] === 'stress') $cultural -= 15;
    if (!empty($app['cv_url'])) $cultural += 8;
    if (mb_strlen($skillsText) > 80) $cultural += 10;
    if (($app['status'] ?? '') === 'entrevista') $cultural += 5;
    $cultural = max(10, min(98, $cultural));

    // Análise de "vídeo/entrevista" simulada a partir de notas (placeholder para visão/áudio)
    $engagement = 50 + min(30, mb_strlen($app['interview_notes'] ?? '') / 4);
    $clarity = 45 + count($candSkills) * 6;
    $confidence = ($sent['sentiment'] === 'positivo') ? 78 : (($sent['sentiment'] === 'negativo') ? 42 : 60);
    $engagement = max(20, min(95, round($engagement, 1)));
    $clarity = max(20, min(95, round($clarity, 1)));
    $confidence = max(20, min(95, round($confidence, 1)));

    $overall = round(($technical * 0.4) + ($cultural * 0.25) + ($engagement * 0.15) + ($clarity * 0.1) + ($confidence * 0.1), 1);

    $gaps = array_values(array_diff($reqSkills, $candSkills));
    $strengths = $candSkills ?: ['Perfil em análise'];

    $name = $app['candidate_name'] ?? ('Candidato #' . ($app['id'] ?? ''));
    $gapText = $gaps ? ('pode precisar de suporte em ' . implode(', ', array_slice($gaps, 0, 3))) : 'alinha bem com os requisitos principais';
    $summary = sprintf(
        'O candidato %s tem %.0f%% de compatibilidade porque %s a stack/competências pedidas%s, mas %s.',
        $name,
        $overall,
        $technical >= 70 ? 'domina boa parte de' : 'cobre parcialmente',
        $strengths ? ' (' . implode(', ', array_slice($strengths, 0, 4)) . ')' : '',
        $gapText
    );

    return [
        'cultural_fit' => round($cultural, 1),
        'technical_fit' => $technical,
        'engagement' => $engagement,
        'clarity' => $clarity,
        'confidence' => $confidence,
        'overall' => $overall,
        'summary' => $summary,
        'strengths' => implode(', ', $strengths),
        'gaps' => implode(', ', $gaps),
        'skills_detected' => $candSkills,
    ];
}

function ml_turnover_risk(PDO $pdo, int $internId): array
{
    $factors = [];
    $score = 15; // base

    // Avaliações baixas
    $avg = $pdo->prepare("SELECT AVG(final_score) AS a, COUNT(*) AS c FROM evaluations WHERE intern_id = ?");
    $avg->execute([$internId]);
    $ev = $avg->fetch(PDO::FETCH_ASSOC);
    $avgScore = $ev && $ev['a'] !== null ? (float)$ev['a'] : null;
    if ($avgScore !== null) {
        if ($avgScore < 3) { $score += 28; $factors[] = 'Avaliações de desempenho baixas (média ' . round($avgScore, 1) . ')'; }
        elseif ($avgScore < 3.5) { $score += 15; $factors[] = 'Desempenho abaixo do esperado'; }
        else { $score -= 5; }
    } else {
        $score += 8;
        $factors[] = 'Sem histórico de avaliações recentes';
    }

    // Tempo na função
    $st = $pdo->prepare("SELECT start_date, cargo, department, scholarship_amount FROM interns WHERE id = ?");
    $st->execute([$internId]);
    $intern = $st->fetch(PDO::FETCH_ASSOC) ?: [];
    if (!empty($intern['start_date'])) {
        $months = (int)((time() - strtotime($intern['start_date'])) / (30 * 86400));
        if ($months >= 18) { $score += 18; $factors[] = "Muito tempo na mesma função (~{$months} meses)"; }
        elseif ($months >= 12) { $score += 10; $factors[] = 'Mais de 1 ano sem progressão aparente'; }
    }

    // Férias fragmentadas
    $vac = $pdo->prepare("SELECT COUNT(*) AS c, SUM(DATEDIFF(end_date, start_date)+1) AS days
        FROM vacations WHERE intern_id = ? AND status = 'aprovado'
        AND start_date >= DATE_SUB(CURDATE(), INTERVAL 12 MONTH)");
    $vac->execute([$internId]);
    $v = $vac->fetch(PDO::FETCH_ASSOC);
    if ($v && (int)$v['c'] >= 3 && (int)$v['days'] < 15) {
        $score += 12;
        $factors[] = 'Férias muito fragmentadas no último ano';
    }

    // Faltas / atrasos
    $att = $pdo->prepare("SELECT
        SUM(status = 'falta') AS faltas,
        SUM(status = 'atraso' OR (check_in IS NOT NULL AND TIME(check_in) > '09:15:00')) AS atrasos
        FROM attendance WHERE intern_id = ? AND date >= DATE_SUB(CURDATE(), INTERVAL 90 DAY)");
    $att->execute([$internId]);
    $a = $att->fetch(PDO::FETCH_ASSOC);
    $faltas = (int)($a['faltas'] ?? 0);
    $atrasos = (int)($a['atrasos'] ?? 0);
    if ($faltas >= 3) { $score += 16; $factors[] = "{$faltas} faltas nos últimos 90 dias"; }
    if ($atrasos >= 5) { $score += 10; $factors[] = "{$atrasos} atrasos recentes"; }

    // Atividades atrasadas
    $act = $pdo->prepare("SELECT COUNT(*) FROM activities WHERE intern_id = ? AND status != 'concluida' AND deadline < CURDATE()");
    $act->execute([$internId]);
    $overdue = (int)$act->fetchColumn();
    if ($overdue >= 2) { $score += 12; $factors[] = "{$overdue} tarefas em atraso"; }

    // Sem aumento de bolsa (proxy: bolsa baixa + tempo)
    if (!empty($intern['scholarship_amount']) && (float)$intern['scholarship_amount'] < 200) {
        $score += 8;
        $factors[] = 'Bolsa/remuneração potencialmente desatualizada';
    }

    $score = max(5, min(95, $score));
    $level = $score >= 65 ? 'alto' : ($score >= 40 ? 'medio' : 'baixo');

    $rec = match ($level) {
        'alto' => 'Ação preventiva urgente: 1:1 com gestor, plano de retenção, revisão salarial/bolsa e carga de trabalho.',
        'medio' => 'Monitorizar: conversa de carreira, feedback positivo e revisão de objetivos em 30 dias.',
        default => 'Risco controlado. Manter check-ins regulares e reconhecimento.',
    };

    if (!$factors) {
        $factors[] = 'Sem sinais críticos relevantes';
    }

    return [
        'intern_id' => $internId,
        'risk_score' => round($score, 1),
        'risk_level' => $level,
        'factors' => $factors,
        'recommendation' => $rec,
        'alert_text' => sprintf(
            'Este colaborador tem %.0f%% de chance de pedir demissão nos próximos 3 meses.',
            $score
        ),
    ];
}

function ml_career_plan(PDO $pdo, int $internId, ?string $targetRole = null): array
{
    $st = $pdo->prepare("
        SELECT i.*, u.name FROM interns i
        JOIN users u ON u.id = i.user_id
        WHERE i.id = ?
    ");
    $st->execute([$internId]);
    $intern = $st->fetch(PDO::FETCH_ASSOC);
    if (!$intern) {
        return ['error' => 'Colaborador não encontrado'];
    }

    $area = $intern['internship_area'] ?: ($intern['department'] ?: 'Tecnologia');
    $cargo = $intern['cargo'] ?: 'Colaborador';
    $target = $targetRole ?: match (true) {
        stripos($area, 'rh') !== false => 'Business Partner de RH',
        stripos($area, 'eng') !== false || stripos($area, 'dev') !== false => 'Gerente de Engenharia',
        default => 'Especialista Sénior em ' . $area,
    };

    $ev = $pdo->prepare("SELECT AVG(score_technical) t, AVG(score_learning) l, AVG(score_teamwork) tw, AVG(final_score) f
        FROM evaluations WHERE intern_id = ?");
    $ev->execute([$internId]);
    $scores = $ev->fetch(PDO::FETCH_ASSOC) ?: [];

    $skills = array_filter([
        $area,
        $cargo,
        ((float)($scores['t'] ?? 0) >= 3.5) ? 'Competência técnica sólida' : 'Técnica a reforçar',
        ((float)($scores['l'] ?? 0) >= 3.5) ? 'Aprendizagem contínua' : 'Curiosidade técnica',
        ((float)($scores['tw'] ?? 0) >= 3.5) ? 'Trabalho em equipa' : 'Comunicação colaborativa',
    ]);

    $courses = [
        "Fundamentos avançados de {$area}",
        'Liderança e gestão de pessoas (essencial para progressão)',
        'Metodologias ágeis e entrega de valor',
    ];
    $projects = [
        "Liderar um projeto prático de melhoria no departamento {$area}",
        'Mentorar um colega júnior durante 4 semanas',
        'Apresentar um case de impacto à direção',
    ];

    $plan = "Para chegar a {$target}, o MeckLittle sugere:\n"
        . "1) Cursos: " . implode('; ', $courses) . ".\n"
        . "2) Projeto prático: {$projects[0]}.\n"
        . "3) Competências atuais: " . implode(', ', $skills) . ".\n"
        . "4) Horizonte: 6–12 meses com checkpoints mensais nas avaliações.";

    return [
        'intern_id' => $internId,
        'name' => $intern['name'],
        'current_role' => $cargo,
        'target_role' => $target,
        'current_skills' => $skills,
        'suggested_courses' => $courses,
        'suggested_projects' => $projects,
        'plan_text' => $plan,
    ];
}

function ml_performance_360(PDO $pdo, int $internId, ?int $actorId = null): array
{
    $st = $pdo->prepare("SELECT i.*, u.name FROM interns i JOIN users u ON u.id = i.user_id WHERE i.id = ?");
    $st->execute([$internId]);
    $intern = $st->fetch(PDO::FETCH_ASSOC);
    if (!$intern) return ['error' => 'Colaborador não encontrado'];

    $evals = $pdo->prepare("SELECT * FROM evaluations WHERE intern_id = ? ORDER BY evaluation_month DESC LIMIT 12");
    $evals->execute([$internId]);
    $rows = $evals->fetchAll(PDO::FETCH_ASSOC);

    $acts = $pdo->prepare("SELECT title, status, progress_percentage, deadline, supervisor_comment
        FROM activities WHERE intern_id = ? ORDER BY deadline DESC LIMIT 20");
    $acts->execute([$internId]);
    $activities = $acts->fetchAll(PDO::FETCH_ASSOC);

    $avg = function ($key) use ($rows) {
        if (!$rows) return null;
        $vals = array_filter(array_map(fn($r) => $r[$key] ?? null, $rows), fn($v) => $v !== null);
        return $vals ? round(array_sum($vals) / count($vals), 2) : null;
    };

    $final = $avg('final_score');
    $done = count(array_filter($activities, fn($a) => ($a['status'] ?? '') === 'concluida'));
    $total = count($activities);

    $strengths = [];
    $improve = [];
    $map = [
        'score_punctuality' => 'pontualidade',
        'score_commitment' => 'compromisso',
        'score_productivity' => 'produtividade',
        'score_teamwork' => 'trabalho em equipa',
        'score_technical' => 'competência técnica',
        'score_learning' => 'capacidade de aprendizagem',
    ];
    foreach ($map as $col => $label) {
        $v = $avg($col);
        if ($v === null) continue;
        if ($v >= 4) $strengths[] = $label;
        elseif ($v < 3.2) $improve[] = $label;
    }

    $comments = array_values(array_filter(array_map(fn($r) => trim((string)($r['comments'] ?? '')), $rows)));
    $comments = array_slice($comments, 0, 5);

    $draft = "Avaliação de Desempenho 360° (rascunho MeckLittle)\n";
    $draft .= "Colaborador: {$intern['name']}\n";
    $draft .= "Período: últimos 12 meses\n\n";
    $draft .= "Síntese equilibrada:\n";
    $draft .= $final !== null
        ? "A média de desempenho no período é {$final}/5. "
        : "Ainda há poucas avaliações quantitativas registadas. ";
    $draft .= $total
        ? "Nas entregas recentes, concluiu {$done} de {$total} atividades acompanhadas. "
        : "Não há atividades suficientes para inferir ritmo de entrega. ";
    $draft .= "\n\nPontos fortes (baseados em dados, sem viés emocional):\n";
    $draft .= $strengths ? '- ' . implode("\n- ", $strengths) : '- Consistência operacional a confirmar com mais dados';
    $draft .= "\n\nÁreas de desenvolvimento:\n";
    $draft .= $improve ? '- ' . implode("\n- ", $improve) : '- Manter o nível atual e definir um desafio de stretch';
    if ($comments) {
        $draft .= "\n\nFeedbacks estruturados registados:\n- " . implode("\n- ", $comments);
    }
    $draft .= "\n\nRecomendação ao gestor: validar este rascunho, ajustar exemplos concretos e partilhar em 1:1. ";
    $draft .= "O texto evita adjetivos emocionais e baseia-se em métricas e histórico de entregas.";

    $period = date('Y');
    $ins = $pdo->prepare("INSERT INTO mecklittle_reviews (intern_id, period_label, draft_text, sources, created_by) VALUES (?,?,?,?,?)");
    $ins->execute([
        $internId,
        $period,
        $draft,
        json_encode(['evaluations' => count($rows), 'activities' => $total], JSON_UNESCAPED_UNICODE),
        $actorId,
    ]);

    ml_privacy_log($pdo, 'review_360_draft', $actorId, (int)$intern['user_id'], 'Rascunho 360 gerado', false);

    return [
        'intern_id' => $internId,
        'name' => $intern['name'],
        'period' => $period,
        'draft_text' => $draft,
        'averages' => [
            'final' => $final,
            'punctuality' => $avg('score_punctuality'),
            'commitment' => $avg('score_commitment'),
            'productivity' => $avg('score_productivity'),
            'teamwork' => $avg('score_teamwork'),
            'technical' => $avg('score_technical'),
            'learning' => $avg('score_learning'),
        ],
    ];
}

function ml_copilot_answer(PDO $pdo, string $question, int $userId): array
{
    $q = mb_strtolower(trim($question), 'UTF-8');
    $kb = $pdo->query("SELECT * FROM mecklittle_knowledge WHERE is_public = 1")->fetchAll(PDO::FETCH_ASSOC);

    $best = null;
    $bestScore = 0;
    foreach ($kb as $row) {
        $hay = mb_strtolower($row['title'] . ' ' . $row['content'] . ' ' . ($row['tags'] ?? ''), 'UTF-8');
        $score = 0;
        foreach (preg_split('/\s+/', $q) as $token) {
            if (mb_strlen($token) < 3) continue;
            if (mb_strpos($hay, $token) !== false) $score += 2;
        }
        if (mb_strpos($q, mb_strtolower($row['category'], 'UTF-8')) !== false) $score += 3;
        if ($score > $bestScore) {
            $bestScore = $score;
            $best = $row;
        }
    }

    // Intents especiais
    if (preg_match('/f[eé]rias|saldo/', $q)) {
        try {
            $intern = $pdo->prepare("SELECT id FROM interns WHERE user_id = ? LIMIT 1");
            $intern->execute([$userId]);
            $iid = $intern->fetchColumn();
            if ($iid) {
                $used = $pdo->prepare("SELECT COALESCE(SUM(DATEDIFF(end_date,start_date)+1),0) FROM vacations WHERE intern_id = ? AND status = 'aprovado' AND YEAR(start_date)=YEAR(CURDATE())");
                $used->execute([$iid]);
                $daysUsed = (int)$used->fetchColumn();
                $saldo = max(0, 22 - $daysUsed);
                return [
                    'answer' => "Consultei os seus registos: este ano tem aproximadamente **{$saldo} dias** de férias disponíveis (base 22 dias − {$daysUsed} aprovados). Para pedir, use o módulo Férias com 15 dias de antecedência.",
                    'source' => 'dados + política de férias',
                    'intent' => 'ferias_saldo',
                ];
            }
        } catch (Throwable $e) { /* fallthrough */ }
    }

    if (preg_match('/turnover|rotatividade|risco/', $q)) {
        return [
            'answer' => 'No módulo **People Analytics** do MeckLittle pode ver alertas de turnover preditivo por colaborador, com fatores explicáveis e recomendações preventivas para gestores.',
            'source' => 'people_analytics',
            'intent' => 'turnover',
        ];
    }

    if (preg_match('/lgpd|rgpd|privacidade|dados pessoais/', $q)) {
        return [
            'answer' => 'O MeckLittle segue princípios LGPD/RGPD: minimização de dados, logs de acesso a análises sensíveis, anonimização em relatórios agregados e revisão humana obrigatória em decisões de alto impacto (contratação/desligamento). Para exercer direitos, abra um Chamado RH — Privacidade.',
            'source' => 'lgpd',
            'intent' => 'privacy',
        ];
    }

    if ($best && $bestScore >= 2) {
        return [
            'answer' => "**{$best['title']}**\n\n{$best['content']}\n\n_Se precisar, posso detalhar o passo a passo no sistema._",
            'source' => $best['category'],
            'intent' => 'knowledge',
        ];
    }

    return [
        'answer' => "Sou o **MeckLittle**, o agente de RH preditivo da Meck. Posso ajudar com:\n"
            . "• Políticas, benefícios, férias e reembolsos\n"
            . "• Recrutamento preditivo e rankeamento de candidatos\n"
            . "• Risco de turnover e planos de carreira\n"
            . "• Avaliações 360°, onboarding e pulse surveys\n\n"
            . "Reformule a pergunta ou abra o módulo correspondente no hub MeckLittle.",
        'source' => 'fallback',
        'intent' => 'fallback',
    ];
}

function ml_ensure_onboarding(PDO $pdo, int $internId): void
{
    $exists = $pdo->prepare("SELECT COUNT(*) FROM mecklittle_onboarding WHERE intern_id = ?");
    $exists->execute([$internId]);
    if ((int)$exists->fetchColumn() > 0) return;

    $tasks = [
        [1, 'Bem-vindo à Meck', 'Ler a mensagem de boas-vindas e confirmar dados do perfil.'],
        [1, 'Conhecer a equipa', 'Agendar um café virtual de 15 min com o supervisor.'],
        [3, 'Políticas essenciais', 'Ler políticas de férias, presenças e privacidade (LGPD).'],
        [5, 'Acessos e ferramentas', 'Validar email, chat Meck e módulo de documentos.'],
        [7, 'Primeira entrega', 'Concluir a primeira tarefa atribuída no módulo Atividades.'],
        [14, 'Check-in de adaptação', 'Responder ao pulse survey: “Como está a sua adaptação?”'],
        [21, 'Feedback ao gestor', 'Partilhar 1 desafio e 1 vitória da 3.ª semana.'],
        [30, 'Revisão dos 30 dias', 'Reunião de fecho do onboarding com o RH/supervisor.'],
    ];
    $ins = $pdo->prepare("INSERT INTO mecklittle_onboarding (intern_id, day_number, title, description, status) VALUES (?,?,?,?,?)");
    foreach ($tasks as $i => $t) {
        $status = $i === 0 ? 'pendente' : ($t[0] > 1 ? 'bloqueada' : 'pendente');
        if ($i <= 1) $status = 'pendente';
        $ins->execute([$internId, $t[0], $t[1], $t[2], $status]);
    }
}
