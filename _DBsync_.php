<?php
/**
 * _DBsync_
 * PHP app for synchronization of registrations and grades, between Moodle
 * and WISEflow databases (MySQL) and academic management system (Oracle).
 * (developed for UAb - Universidade Aberta)
 *
 * @package    _DBsync_
 * @category   app
 * @author     Bruno Tavares <brunustavares@gmail.com>
 * @link       https://www.linkedin.com/in/brunomastavares/
 * @copyright  Copyright (C) 2024-2025 Bruno Tavares
 * @license    GNU General Public License v3 or later
 *             https://www.gnu.org/licenses/gpl-3.0.html
 * @version    2025072811
 * @date       2024-04-04
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program. If not, see <https://www.gnu.org/licenses/>.
 */

 define('CLI_SCRIPT', true);

 require_once './auth_lib_dbs.php';

 date_default_timezone_set('Europe/Lisbon');

/**
 * Verifica a origem da chamada: CLI ou browser
 *
 * @return boolean
 */
function Is_cli()
{
    if (defined('STDIN')
        || (empty($_SERVER['REMOTE_ADDR'])
        && !isset($_SERVER['HTTP_USER_AGENT'])
        && count($_SERVER['argv']) > 0)
    ) {
        return true;

    }

    return false;

}

 header("Cache-Control: no-cache, must-revalidate");
 header("Pragma: no-cache");

 $lectyear = (date("Y") -1) . date("y");

// quebras de linha nas mensagens, em função do ambiente gráfico de chamada
    $nl = "";

    if (Is_cli()) {
        $nl = "\n";

    } else {
        $nl = "<br>";

    }

 // Inicializar conexão à BDInt
 $bdint = connect2bdint();

 // obtém data e hora da última execução + envio de alerta de paragem
    $get_last_run = "SELECT lead.sync_siges_app.last_run, alert
                     FROM lead.sync_siges_app
                     WHERE lead.sync_siges_app.name = 'notas'";

    $result = mysqli_query($bdint, $get_last_run)
                  or die('erro na consulta(00) da BDInt: ' . mysqli_error($bdint) .
                          $nl . $nl . $get_last_run);

    while ($row = mysqli_fetch_assoc($result)) {
        $last_run = $row['last_run'];
        $alert = $row['alert'];

    }

    $expected_run = $last_run + 1860;
    $alert_time = $last_run + 7200;

    if ($expected_run > strtotime("now")) {
        $color = "green";

    } else {
        $color = "red";
        $alert_msg = "ALERTA: servico parado";

        if ($alert_time <= strtotime("now")
            && $alert == 0) {
            $email->Subject = $alert_msg;
            $email->Body = '<br>ultima execucao: <span style="color: ' . $color . '; font-weight: bold;">' . date("Y-m-d H:i:s", $last_run) . '</span>
                            <br><br>(sent automatically from root@00app10)';

            if ($email->Send()) {
                echo $nl . 'INFO: e-mail enviado' . $nl;

                $set_alert = "UPDATE lead.sync_siges_app
                              SET alert = 1
                              WHERE name = 'notas'";

                mysqli_query($bdint, $set_alert)
                    or die('erro na actualização(13) da BDInt: ' . mysqli_error($bdint) .
                            $nl . $nl . $set_alert);

                $alert == 1;

            } else {
                echo $nl . 'ALERTA: e-mail NAO enviado | erro: ' . $email->ErrorInfo . $nl;

            }

        }

        // exit($nl . $alert_msg . $nl);

    }

$main = '<html>
            <title>_DBsync_</title>
            
            <head>
                <link rel="stylesheet" href="styles.css">
                <link rel="shortcut icon" href="./logo_DBsync_.png" type="image/x-icon"/>

                <script src="https://cdn.rawgit.com/kimmobrunfeldt/progressbar.js/1.0.0/dist/progressbar.js"></script>
                <script src="functions.js"></script>

            </head>

         <body>
            <div class="main">
                <div class="logo_div">
                    <img class="logo_pic" src="./logo_DBsync_.png" title="logo" alt="logo">

                    <table class="info_tbl">
                        <tr>
                            <td>
                                <label class="last_run" id="last_run" name="last_run">sincroniza&#xE7;&#xE3;o anterior: <span style="color: ' . $color . '; font-weight: bold;">' . date("Y-m-d H:i:s", $last_run) . '</span></label>
                            </td>
                            
                            <td>
                                <div id="div_cr">
                                    <a title="desenvolvido por..."
                                        href="https://www.linkedin.com/in/brunomastavares/"
                                        target = "_blank">
                                        2024
                                    </a>
                                </div>
                            </td>

                        </tr>

                    </table>

                </div>

                <hr>

                <div class="results_div">
                    <br>';

if (!Is_cli()) { echo $main; }

$delay = 500;
$tRecs = 0;
$tEval = 0;

 // Inicializar conexão ao SiGES
 $siges = connect2siges();
 
// REG.AVALIAÇÃO (PlataformAbERTA->BDInt<->SiGES)
    $title = "REG.AVALIAÇÃO (PlataformAbERTA->BDInt<->SiGES)";

    $i = 0;
    $j = 0;

    if (!Is_cli()) {
        echo '<div id="eval">
                  <script type="text/javascript">
                      add_title("eval", "10", "' . $title . '", ' . $delay . ', "darkorange");
                  </script>';

    }

    // Inicializar avaliação dos estudantes, na BDInt
        $set_Eval = "UPDATE lead.alunos_inscricoes
                     SET MODO_AVA_N = 'F'
                     WHERE CD_LECTIVO >= '" . $lectyear . "'
                         AND MODO_AVA IS NOT NULL
                         AND MODO_AVA_N IS NULL";

        mysqli_query($bdint, $set_Eval)
            or die('erro na actualização(11) da BDInt: ' . mysqli_error($bdint) .
                    $nl . $nl . $set_Eval);

        if (mysqli_affected_rows($bdint) > 0) { $j++; $tEval++; }

    // Rectificar avaliação dos estudantes AMPV, na BDInt
        $reset_Aprov = "UPDATE lead.alunos_inscricoes
                        SET MODO_AVA = 'F'
                        WHERE CD_LECTIVO >= '" . $lectyear . "'
                            AND TURMA_MOODLE LIKE '%AMPV'
                            AND MODO_AVA = 'C'";

        mysqli_query($bdint, $reset_Aprov)
            or die('erro na actualização(18) da BDInt: ' . mysqli_error($bdint) .
                    $nl . $nl . $reset_Aprov);

        if (mysqli_affected_rows($bdint) > 0) { $j++; $tEval++; }

    // Rectificar avaliação dos estudantes com aprovação, na BDInt
        $reset_Aprov = "UPDATE lead.alunos_inscricoes
                        SET MODO_AVA_R = 'A'
                        WHERE CD_LECTIVO >= '" . $lectyear . "'
                            AND NR_AVALIA >= 10
                            AND MODO_AVA_R <> 'A'";

        mysqli_query($bdint, $reset_Aprov)
            or die('erro na actualização(17) da BDInt: ' . mysqli_error($bdint) .
                    $nl . $nl . $reset_Aprov);

        if (mysqli_affected_rows($bdint) > 0) { $j++; $tEval++; }

    // Obter estudantes de Avaliação Final por opção, na BDInt
    // para registo no SiGES
        $recs_2sync = array();

        $get_recs_2sync = "SELECT CD_LECTIVO, CD_ALUNO, CD_CURSO, CD_DISCIP, MODO_AVA, MODO_AVA_N, MODO_AVA_R, EPOCA, INICIO
                           FROM lead.mv_alunos_flows
                           WHERE MODO_AVA = 'F'
                               AND INICIO >= NOW()
                           ORDER BY RAND()
                           LIMIT 500";

        $result = mysqli_query($bdint, $get_recs_2sync)
                      or die('erro na consulta(14) da BDInt: ' . mysqli_error($bdint) .
                              $nl . $nl . $get_recs_2sync);

        if (mysqli_num_rows($result) > 0) {
            $stdts = "";
            $UCs = "";

            while ($row = mysqli_fetch_assoc($result)) {
                $recs_2sync[] = $row;

                if (!strstr($stdts, $row['CD_ALUNO'])) {
                    if ($stdts <> "") {
                        $stdts .= ",";

                    }
                    
                    $stdts .= $row['CD_ALUNO'];
                
                }

                if (!strstr($UCs, $row['CD_DISCIP'])) {
                    if ($UCs <> "") {
                        $UCs .= ",";

                    }
                    
                    $UCs .= $row['CD_DISCIP'];
                
                }

            }

            // Obter respectivos registos de inscrição, no SiGES
                $insc_recs = array();

                $get_insc_recs = "SELECT CD_LECTIVO, CD_ALUNO, CD_CURSO, CD_DISCIP, CD_DURACAO, CD_LOCAL
                                  FROM CSE.T_AVALUNO
                                  WHERE CD_LECTIVO = '" . $lectyear . "'
                                      AND CD_ALUNO IN (" . $stdts . ")
                                      AND CD_DISCIP IN (" . $UCs . ")
                                      AND CD_GRU_AVA IN (1, 43)
                                      AND PROTEGIDO = 'N'
                                  ORDER BY CD_ALUNO ASC, CD_DISCIP ASC";

                $stmt = oci_parse($siges, $get_insc_recs);

                if (!$stmt) {
                    $error = oci_error($siges);

                    echo "erro de analise(13): " . $error['message'] .
                          $nl . $nl . $get_insc_recs;

                    exit;

                }

                $result = oci_execute($stmt);

                if (!$result) {
                    $error = oci_error($stmt);

                    echo "erro na consulta(08) do SiGES: " . $error['message'] .
                          $nl . $nl . $get_insc_recs;

                    exit;

                }

                while (($row = oci_fetch_array($stmt, OCI_ASSOC+OCI_RETURN_NULLS)) != false) {
                    $insc_recs[] = $row;

                }

                oci_free_statement($stmt);

            $tRecs = count($recs_2sync) * count($insc_recs);

            if ($tRecs == 0) { $tRecs = 1000; }

            if (!empty($insc_recs) > 0) {
                // Percorrer o array de avaliações a sincronizar
                foreach ($recs_2sync as $rec_2sync) {
                    // Percorrer o array de inscrições registadas
                    foreach ($insc_recs as $insc) {
                        $eval_recs = array();

                        if ($insc['CD_LECTIVO'] == $rec_2sync['CD_LECTIVO']
                            && $insc['CD_ALUNO'] == $rec_2sync['CD_ALUNO']
                            && $insc['CD_CURSO'] == $rec_2sync['CD_CURSO']
                            && $insc['CD_DISCIP'] == $rec_2sync['CD_DISCIP']) {
                            if ($rec_2sync['EPOCA'] == 'N') {
                                $CD_GRU_AVA = 1;

                            } elseif ($rec_2sync['EPOCA'] == 'R') {
                                $CD_GRU_AVA = 2;

                            }

                            // Obter respectivo registo de avaliação, no SiGES
                                $eval_recs = array();

                                $get_eval_recs = "SELECT CD_LECTIVO, CD_ALUNO, CD_CURSO, CD_DISCIP, CD_STA_EPO, CD_GRU_AVA
                                                  FROM CSE.T_AVALUNO
                                                  WHERE CD_LECTIVO = '" . $insc['CD_LECTIVO'] . "'
                                                      AND CD_ALUNO = '" . $insc['CD_ALUNO'] . "'
                                                      AND CD_CURSO = '" . $insc['CD_CURSO'] . "'
                                                      AND CD_DISCIP = '" . $insc['CD_DISCIP'] . "'
                                                      AND CD_STA_EPO IN (1, 4, 7)
                                                      AND to_char(CSE.T_AVALUNO.DT_AVALIA, 'YYYY-MM-DD') >= to_char(sysdate, 'YYYY-MM-DD')";

                                $stmt = oci_parse($siges, $get_eval_recs);

                                if (!$stmt) {
                                    $error = oci_error($siges);

                                    echo "erro de analise(14): " . $error['message'] .
                                          $nl . $nl . $get_eval_recs;

                                    exit;

                                }

                                $result = oci_execute($stmt);

                                if (!$result) {
                                    $error = oci_error($stmt);

                                    echo "erro na consulta(09) do SiGES: " . $error['message'] .
                                          $nl . $nl . $get_eval_recs;

                                    exit;

                                }

                                while (($row = oci_fetch_array($stmt, OCI_ASSOC+OCI_RETURN_NULLS)) != false) {
                                    $eval_recs[] = $row;

                                }

                                oci_free_statement($stmt);

                            if (!empty($eval_recs) > 0) {
                                foreach ($eval_recs as $eval_rec) {
                                    if ($eval_rec['CD_GRU_AVA'] <> $CD_GRU_AVA) {
                                        $updt_eval_rec = "UPDATE CSE.T_AVALUNO
                                                          SET CD_GRU_AVA = '" . $CD_GRU_AVA . "'
                                                          WHERE CD_LECTIVO = '" . $eval_rec['CD_LECTIVO'] . "'
                                                              AND CD_ALUNO = '" . $eval_rec['CD_ALUNO'] . "'
                                                              AND CD_CURSO = '" . $eval_rec['CD_CURSO'] . "'
                                                              AND CD_DISCIP = '" . $eval_rec['CD_DISCIP'] . "'
                                                              AND CD_STA_EPO = '" . $eval_rec['CD_STA_EPO'] . "'
                                                              AND to_char(CSE.T_AVALUNO.DT_AVALIA, 'YYYY-MM-DD') >= to_char(sysdate, 'YYYY-MM-DD')
                                                              AND CD_GRU_AVA <> '" . $CD_GRU_AVA . "'";

                                        $stmt = oci_parse($siges, $updt_eval_rec);

                                        if (!$stmt) {
                                            $error = oci_error($siges);
            
                                            echo "erro de analise(15): " . $error['message'] .
                                                  $nl . $nl . $updt_eval_rec;
            
                                            exit;
            
                                        }
            
                                        $result = oci_execute($stmt, OCI_COMMIT_ON_SUCCESS);
            
                                        if (!$result) {
                                            $error = oci_error($stmt);
            
                                            echo "erro na actualização(06) do SiGES: " . $error['message'] .
                                                  $nl . $nl . $updt_eval_rec;
            
                                            exit;
            
                                        }
            
                                        if (oci_num_rows($stmt) > 0) { $i++; $tEval++; }

                                        oci_free_statement($stmt);

                                    }

                                }

                            } else {
                                $check_aprov_recs = "SELECT *
                                                     FROM CSE.T_AVALUNO
                                                     WHERE CD_LECTIVO = '" . $insc['CD_LECTIVO'] . "'
                                                         AND CD_ALUNO = '" . $insc['CD_ALUNO'] . "'
                                                         AND CD_DISCIP = '" . $insc['CD_DISCIP'] . "'
                                                         AND CD_STA_EPO = 3";

                                $stmt = oci_parse($siges, $check_aprov_recs);

                                if (!$stmt) {
                                    $error = oci_error($siges);

                                    echo "erro de analise(21): " . $error['message'] .
                                          $nl . $nl . $check_aprov_recs;

                                    exit;

                                }

                                $result = oci_execute($stmt);

                                if (!$result) {
                                    $error = oci_error($stmt);

                                    echo "erro na consulta(12) do SiGES: " . $error['message'] .
                                          $nl . $nl . $check_aprov_recs;

                                    exit;

                                }

                                while (($row = oci_fetch_array($stmt, OCI_ASSOC+OCI_RETURN_NULLS)) != false) {
                                    $aprov_recs[] = $row;

                                }

                                oci_free_statement($stmt);

                                if (empty($aprov_recs) > 0) {
                                    $isrt_eval_rec = "INSERT INTO CSE.T_AVALUNO(CD_LECTIVO,
                                                                                CD_CURSO,
                                                                                CD_ALUNO,
                                                                                CD_DISCIP,
                                                                                CD_DURACAO,
                                                                                CD_GRU_AVA,
                                                                                CD_AVALIA,
                                                                                DT_AVALIA,
                                                                                CD_STA_EPO,
                                                                                CD_FINAL,
                                                                                PROTEGIDO,
                                                                                CD_LOCAL)
                                                      VALUES ('" . $rec_2sync['CD_LECTIVO'] . "',
                                                              '" . $rec_2sync['CD_CURSO'] . "',
                                                              '" . $rec_2sync['CD_ALUNO'] . "',
                                                              '" . $rec_2sync['CD_DISCIP'] . "',
                                                              '" . $insc['CD_DURACAO'] . "',
                                                              '" . $CD_GRU_AVA . "',
                                                              '99',
                                                              to_date('" . $rec_2sync['INICIO'] . "','YYYY-MM-DD HH24:MI:SS'),
                                                              '1',
                                                              'N',
                                                              'N',
                                                              '" . $insc['CD_LOCAL'] . "')";

                                    $stmt = oci_parse($siges, $isrt_eval_rec);

                                    if (!$stmt) {
                                        $error = oci_error($siges);

                                        echo "erro de analise(16): " . $error['message'] .
                                              $nl . $nl . $isrt_eval_rec;

                                        exit;

                                    }

                                    $result = oci_execute($stmt, OCI_COMMIT_ON_SUCCESS);

                                    if (!$result) {
                                        $error = oci_error($stmt);

                                        echo "erro na actualização(07) do SiGES: " . $error['message'] .
                                              $nl . $nl . $isrt_eval_rec;

                                        exit;

                                    }

                                    if (oci_num_rows($stmt) > 0) { $i++; $tEval++; }

                                    oci_free_statement($stmt);

                                } else {
                                    foreach ($aprov_recs as $aprov_rec) {
                                        $set_Grade = "UPDATE lead.alunos_inscricoes
                                                      SET NR_AVALIA = " . (float)$aprov_rec['NR_AVALIA'] . ", STATUS_AVALIA = 'S'
                                                      WHERE CD_LECTIVO = '" . $aprov_rec['CD_LECTIVO'] . "'
                                                          AND CD_CURSO = '" . $aprov_rec['CD_CURSO'] . "'
                                                          AND CD_DISCIP = '" . $aprov_rec['CD_DISCIP'] . "'
                                                          AND CD_ALUNO = '" . $aprov_rec['CD_ALUNO'] . "'
                                                          AND (NR_AVALIA IS NULL OR NR_AVALIA < " . $aprov_rec['NR_AVALIA'] . ")";

                                        mysqli_query($bdint, $set_Grade)
                                            or die('erro na actualização(15) da BDInt: ' . mysqli_error($bdint) .
                                                    $nl . $nl . $set_Grade);

                                        if (mysqli_affected_rows($bdint) > 0) { $j++; $tEval++; }

                                    }

                                }

                            }

                        }

                    }

                }

            }

        }
        
    // Obter estudantes de Avaliação Contínua, com prova a realizar em 8 dias
    // para rectificação de método de avaliação na BDInt
        $recs_2sync = array();

        // $get_recs_2sync = "SELECT ainsc.CD_LECTIVO,
        //                           ainsc.CD_ALUNO,
        //                           ainsc.CD_CURSO,
        //                           ainsc.CD_DISCIP,
        //                           ainsc.MODO_AVA,
        //                           ainsc.MODO_AVA_N,
        //                           ainsc.MODO_AVA_R,
        //                           wflow.dtfrom AS INICIO
        //                    FROM lead.alunos_inscricoes ainsc
        //                        INNER JOIN wiseflow.flows wflow ON (SUBSTR(wflow.subtitle, 1, 5) = ainsc.CD_DISCIP AND wflow.lectyear = ainsc.CD_LECTIVO)
        //                    WHERE ainsc.MODO_AVA = 'C'
        //                        AND wflow.evaltype = 'E'
        //                        AND (wflow.dtfrom >= NOW()
        //                            AND DATE(wflow.dtfrom) <= DATE(NOW() + INTERVAL 8 DAY))
        //                    ORDER BY RAND()
        //                    LIMIT 2500";

        $get_recs_2sync = "SELECT ainsc.CD_LECTIVO,
                                  ainsc.CD_ALUNO,
                                  ainsc.CD_CURSO,
                                  ainsc.CD_DISCIP,
                                  ainsc.MODO_AVA,
                                  ainsc.MODO_AVA_N,
                                  ainsc.MODO_AVA_R,
                                  wflow.dtfrom AS INICIO
                           FROM lead.alunos_inscricoes ainsc
                               INNER JOIN wiseflow.flows wflow ON (SUBSTR(wflow.subtitle, 1, 5) = ainsc.CD_DISCIP AND wflow.lectyear = ainsc.CD_LECTIVO)
                           WHERE (ainsc.MODO_AVA = 'C'
							       AND (MODO_AVA_R IS NULL OR MODO_AVA_R <> 'A'))
                               AND (wflow.dtfrom >= NOW()
                                   AND DATE(wflow.dtfrom) <= DATE(NOW() + INTERVAL 8 DAY))
                           ORDER BY RAND()
                           LIMIT 2500";

        $result = mysqli_query($bdint, $get_recs_2sync)
                      or die('erro na consulta(15) da BDInt: ' . mysqli_error($bdint) .
                              $nl . $nl . $get_recs_2sync);

        if (mysqli_num_rows($result) > 0) {
            $stdts = "";
            $UCs = "";

            while ($row = mysqli_fetch_assoc($result)) {
                $recs_2sync[] = $row;

                if (!strstr($stdts, $row['CD_ALUNO'])) {
                    if ($stdts <> "") {
                        $stdts .= ",";

                    }
                    
                    $stdts .= $row['CD_ALUNO'];
                
                }

                if (!strstr($UCs, $row['CD_DISCIP'])) {
                    if ($UCs <> "") {
                        $UCs .= ",";

                    }
                    
                    $UCs .= $row['CD_DISCIP'];
                
                }

            }

            // Obter avaliação dos estudantes na PlataformAbERTA

                // Inicializar conexão à PlataformAbERTA
                $mdl_std_eval = connect2mdl('estudantes_avaliacao', $lectyear, NULL, $UCs);

                $curl_mdl = curl_init();

                curl_setopt($curl_mdl, CURLOPT_URL, $mdl_std_eval);
                curl_setopt($curl_mdl, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($curl_mdl, CURLOPT_SSL_VERIFYHOST, 0);
                curl_setopt($curl_mdl, CURLOPT_SSL_VERIFYPEER, false);
                curl_setopt($curl_mdl, CURLOPT_SSLVERSION, CURL_SSLVERSION_TLSv1_2);
                
                if (curl_errno($curl_mdl)) { die('cURL error: ' . curl_error($curl_mdl)); }
                
                $std_eval = json_decode(curl_exec($curl_mdl), true);

                curl_close($curl_mdl);

            $tRecs += count($recs_2sync);

            if ($tRecs == 0) { $tRecs = 1000; }

            foreach ($recs_2sync as $rec_2sync) {
                foreach ($std_eval as $std) {
                    if ($rec_2sync['CD_LECTIVO'] == $std['al']
                        && $rec_2sync['CD_ALUNO'] == $std['stdnum']
                        && $rec_2sync['CD_DISCIP'] == substr($std['ucsname'], 0, 5)) {
                        if ($rec_2sync['MODO_AVA_N'] != $std['aval']) {
                            // Actualiza avaliação do estudante na BDInt, conforme dados da PlataformAbERTA
                            $set_Eval = "UPDATE lead.alunos_inscricoes
                                         SET MODO_AVA_N = '" . $std['aval'] . "'
                                         WHERE CD_LECTIVO = '" . $std['al'] . "'
                                             AND CD_ALUNO = '" . $std['stdnum'] . "'
                                             AND CD_DISCIP = '" . substr($std['ucsname'], 0, 5) . "'";

                            mysqli_query($bdint, $set_Eval)
                                or die('erro na actualização(12) da BDInt: ' . mysqli_error($bdint) .
                                        $nl . $nl . $set_Eval);

                            if (mysqli_affected_rows($bdint) > 0) { $j++; $tEval++; }

                        }

                        break;

                    }

                }

            }

        }

    // Obter estudantes de Avaliação Contínua, com prova a realizar em 7 dias
    // para registo no SiGES
        $recs_2sync = array();

        // $get_recs_2sync = "SELECT CD_LECTIVO, CD_ALUNO, CD_CURSO, CD_DISCIP, MODO_AVA, MODO_AVA_N, MODO_AVA_R, dtfrom AS INICIO
        //                    FROM  lead.alunos_inscricoes AS ainsc
        //                        JOIN wiseflow.flows AS flws ON LEFT(flws.subtitle, 5) = ainsc.CD_DISCIP
        //                    WHERE ainsc.CD_LECTIVO = '" . $lectyear . "'
        //                        AND COALESCE(NULLIF(ainsc.MODO_AVA_R, NULL), ainsc.MODO_AVA_N) = 'C'
        //                        AND (ainsc.MODO_AVA_R IS NULL OR ainsc.MODO_AVA_R <> 'A')
        //                        AND SUBSTRING(flws.subtitle, 10, 1) = 'E'
        //                        AND flws.dtfrom BETWEEN NOW() AND DATE_ADD(NOW(), INTERVAL 7 DAY)
        //                    ORDER BY RAND()
        //                    LIMIT 500";

        $get_recs_2sync = "SELECT CD_LECTIVO, CD_ALUNO, CD_CURSO, CD_DISCIP, MODO_AVA, MODO_AVA_N, MODO_AVA_R, EPOCA, INICIO
                           FROM lead.mv_alunos_flows
                           WHERE MODO_AVA = 'C'
                               AND (INICIO >= NOW()
                                   AND DATE(INICIO) <= DATE(NOW() + INTERVAL 7 DAY))
                           ORDER BY RAND()
                           LIMIT 500";

        $result = mysqli_query($bdint, $get_recs_2sync)
                    or die('erro na consulta(16) da BDInt: ' . mysqli_error($bdint) .
                            $nl . $nl . $get_recs_2sync);

        if (mysqli_num_rows($result) > 0) {
            $stdts = "";
            $UCs = "";

            while ($row = mysqli_fetch_assoc($result)) {
                $recs_2sync[] = $row;

                if (!strstr($stdts, $row['CD_ALUNO'])) {
                    if ($stdts <> "") {
                        $stdts .= ",";

                    }
                    
                    $stdts .= $row['CD_ALUNO'];
                
                }

                if (!strstr($UCs, $row['CD_DISCIP'])) {
                    if ($UCs <> "") {
                        $UCs .= ",";

                    }
                    
                    $UCs .= $row['CD_DISCIP'];
                
                }

            }

            // Obter respectivos registos de inscrição, no SiGES
                $insc_recs = array();

                $get_insc_recs = "SELECT CD_LECTIVO, CD_ALUNO, CD_CURSO, CD_DISCIP, CD_DURACAO, CD_LOCAL
                                  FROM CSE.T_AVALUNO
                                  WHERE CD_LECTIVO = '" . $lectyear . "'
                                      AND CD_ALUNO IN (" . $stdts . ")
                                      AND CD_DISCIP IN (" . $UCs . ")
                                      AND CD_GRU_AVA IN (1, 43)
                                      AND PROTEGIDO = 'N'
                                  ORDER BY CD_ALUNO ASC, CD_DISCIP ASC";

                $stmt = oci_parse($siges, $get_insc_recs);

                if (!$stmt) {
                    $error = oci_error($siges);

                    echo "erro de analise(17): " . $error['message'] .
                          $nl . $nl . $get_insc_recs;

                    exit;

                }

                $result = oci_execute($stmt);

                if (!$result) {
                    $error = oci_error($stmt);

                    echo "erro na consulta(10) do SiGES: " . $error['message'] .
                          $nl . $nl . $get_insc_recs;

                    exit;

                }

                while (($row = oci_fetch_array($stmt, OCI_ASSOC+OCI_RETURN_NULLS)) != false) {
                    $insc_recs[] = $row;

                }

                oci_free_statement($stmt);

            $tRecs += count($recs_2sync) * count($insc_recs);

            if ($tRecs == 0) { $tRecs = 1000; }

            if (!empty($insc_recs) > 0) {
                // Percorrer o array de avaliações a sincronizar
                foreach ($recs_2sync as $rec_2sync) {
                    // Percorrer o array de inscrições registadas
                    foreach ($insc_recs as $insc) {
                        $eval_recs = array();

                        if ($insc['CD_LECTIVO'] == $rec_2sync['CD_LECTIVO']
                            && $insc['CD_ALUNO'] == $rec_2sync['CD_ALUNO']
                            && $insc['CD_CURSO'] == $rec_2sync['CD_CURSO']
                            && $insc['CD_DISCIP'] == $rec_2sync['CD_DISCIP']) {
                            if ($rec_2sync['MODO_AVA_R'] == NULL) {
                                if ($rec_2sync['MODO_AVA_N'] == 'C') {
                                    $CD_GRU_AVA = 9;
                                    
                                } else {
                                    $CD_GRU_AVA = 1;

                                }

                            } else {
                                if ($rec_2sync['MODO_AVA_R'] == 'C') {
                                    $CD_GRU_AVA = 10;
                                    
                                } elseif ($rec_2sync['MODO_AVA_R'] == 'F') {
                                    $CD_GRU_AVA = 2;

                                }

                            }

                            // Obter respectivo registo de avaliação, no SiGES
                                $eval_recs = array();

                                $get_eval_recs = "SELECT CD_LECTIVO, CD_ALUNO, CD_CURSO, CD_DISCIP, CD_STA_EPO, CD_GRU_AVA
                                                  FROM CSE.T_AVALUNO
                                                  WHERE CD_LECTIVO = '" . $insc['CD_LECTIVO'] . "'
                                                      AND CD_ALUNO = '" . $insc['CD_ALUNO'] . "'
                                                      AND CD_CURSO = '" . $insc['CD_CURSO'] . "'
                                                      AND CD_DISCIP = '" . $insc['CD_DISCIP'] . "'
                                                      AND CD_STA_EPO IN (1, 4, 7, 16)
                                                      AND to_char(CSE.T_AVALUNO.DT_AVALIA, 'YYYY-MM-DD') >= to_char(sysdate, 'YYYY-MM-DD')";

                                $stmt = oci_parse($siges, $get_eval_recs);

                                if (!$stmt) {
                                    $error = oci_error($siges);

                                    echo "erro de analise(18): " . $error['message'] .
                                          $nl . $nl . $get_eval_recs;

                                    exit;

                                }

                                $result = oci_execute($stmt);

                                if (!$result) {
                                    $error = oci_error($stmt);

                                    echo "erro na consulta(11) do SiGES: " . $error['message'] .
                                          $nl . $nl . $get_eval_recs;

                                    exit;

                                }

                                while (($row = oci_fetch_array($stmt, OCI_ASSOC+OCI_RETURN_NULLS)) != false) {
                                    $eval_recs[] = $row;

                                }

                                oci_free_statement($stmt);

                            if (!empty($eval_recs) > 0) {
                                foreach ($eval_recs as $eval_rec) {
                                    if ($eval_rec['CD_GRU_AVA'] <> $CD_GRU_AVA) {
                                        $updt_eval_rec = "UPDATE CSE.T_AVALUNO
                                                          SET CD_GRU_AVA = '" . $CD_GRU_AVA . "'
                                                          WHERE CD_LECTIVO = '" . $eval_rec['CD_LECTIVO'] . "'
                                                              AND CD_ALUNO = '" . $eval_rec['CD_ALUNO'] . "'
                                                              AND CD_CURSO = '" . $eval_rec['CD_CURSO'] . "'
                                                              AND CD_DISCIP = '" . $eval_rec['CD_DISCIP'] . "'
                                                              AND CD_STA_EPO = '" . $eval_rec['CD_STA_EPO'] . "'
                                                              AND to_char(CSE.T_AVALUNO.DT_AVALIA, 'YYYY-MM-DD') >= to_char(sysdate, 'YYYY-MM-DD')
                                                              AND CD_GRU_AVA <> '" . $CD_GRU_AVA . "'";

                                        $stmt = oci_parse($siges, $updt_eval_rec);

                                        if (!$stmt) {
                                            $error = oci_error($siges);
            
                                            echo "erro de analise(19): " . $error['message'] .
                                                  $nl . $nl . $updt_eval_rec;
            
                                            exit;
            
                                        }
            
                                        $result = oci_execute($stmt, OCI_COMMIT_ON_SUCCESS);
            
                                        if (!$result) {
                                            $error = oci_error($stmt);
            
                                            echo "erro na actualização(08) do SiGES: " . $error['message'] .
                                                  $nl . $nl . $updt_eval_rec;
            
                                            exit;
            
                                        }
            
                                        if (oci_num_rows($stmt) > 0) { $i++; $tEval++; }

                                        oci_free_statement($stmt);

                                    }

                                }

                            } else {
                                $check_aprov_recs = "SELECT *
                                                     FROM CSE.T_AVALUNO
                                                     WHERE CD_LECTIVO = '" . $insc['CD_LECTIVO'] . "'
                                                         AND CD_ALUNO = '" . $insc['CD_ALUNO'] . "'
                                                         AND CD_DISCIP = '" . $insc['CD_DISCIP'] . "'
                                                         AND CD_STA_EPO = 3";

                                $stmt = oci_parse($siges, $check_aprov_recs);

                                if (!$stmt) {
                                    $error = oci_error($siges);

                                    echo "erro de analise(22): " . $error['message'] .
                                          $nl . $nl . $check_aprov_recs;

                                    exit;

                                }

                                $result = oci_execute($stmt);

                                if (!$result) {
                                    $error = oci_error($stmt);

                                    echo "erro na consulta(13) do SiGES: " . $error['message'] .
                                          $nl . $nl . $check_aprov_recs;

                                    exit;

                                }

                                while (($row = oci_fetch_array($stmt, OCI_ASSOC+OCI_RETURN_NULLS)) != false) {
                                    $aprov_recs[] = $row;

                                }

                                oci_free_statement($stmt);

                                if (empty($aprov_recs) > 0) {
                                    $isrt_eval_rec = "INSERT INTO CSE.T_AVALUNO(CD_LECTIVO,
                                                                                CD_CURSO,
                                                                                CD_ALUNO,
                                                                                CD_DISCIP,
                                                                                CD_DURACAO,
                                                                                CD_GRU_AVA,
                                                                                CD_AVALIA,
                                                                                DT_AVALIA,
                                                                                CD_STA_EPO,
                                                                                CD_FINAL,
                                                                                PROTEGIDO,
                                                                                CD_LOCAL)
                                                      VALUES ('" . $rec_2sync['CD_LECTIVO'] . "',
                                                              '" . $rec_2sync['CD_CURSO'] . "',
                                                              '" . $rec_2sync['CD_ALUNO'] . "',
                                                              '" . $rec_2sync['CD_DISCIP'] . "',
                                                              '" . $insc['CD_DURACAO'] . "',
                                                              '" . $CD_GRU_AVA . "',
                                                              '99',
                                                              to_date('" . $rec_2sync['INICIO'] . "','YYYY-MM-DD HH24:MI:SS'),
                                                              '1',
                                                              'N',
                                                              'N',
                                                              '" . $insc['CD_LOCAL'] . "')";

                                    $stmt = oci_parse($siges, $isrt_eval_rec);

                                    if (!$stmt) {
                                        $error = oci_error($siges);

                                        echo "erro de analise(20): " . $error['message'] .
                                              $nl . $nl . $isrt_eval_rec;

                                        exit;

                                    }

                                    $result = oci_execute($stmt, OCI_COMMIT_ON_SUCCESS);

                                    if (!$result) {
                                        $error = oci_error($stmt);

                                        echo "erro na actualização(09) do SiGES: " . $error['message'] .
                                              $nl . $nl . $isrt_eval_rec;

                                        exit;

                                    }

                                    if (oci_num_rows($stmt) > 0) { $i++; $tEval++; }

                                    oci_free_statement($stmt);

                                } else {
                                    foreach ($aprov_recs as $aprov_rec) {
                                        $set_Grade = "UPDATE lead.alunos_inscricoes
                                                      SET NR_AVALIA = " . (float)$aprov_rec['NR_AVALIA'] . ", STATUS_AVALIA = 'S'
                                                      WHERE CD_LECTIVO = '" . $aprov_rec['CD_LECTIVO'] . "'
                                                          AND CD_CURSO = '" . $aprov_rec['CD_CURSO'] . "'
                                                          AND CD_DISCIP = '" . $aprov_rec['CD_DISCIP'] . "'
                                                          AND CD_ALUNO = '" . $aprov_rec['CD_ALUNO'] . "'
                                                          AND (NR_AVALIA IS NULL OR NR_AVALIA < " . $aprov_rec['NR_AVALIA'] . ")";

                                        mysqli_query($bdint, $set_Grade)
                                            or die('erro na actualização(16) da BDInt: ' . mysqli_error($bdint) .
                                                    $nl . $nl . $set_Grade);

                                        if (mysqli_affected_rows($bdint) > 0) { $j++; $tEval++; }

                                    }

                                }

                            }

                        }

                    }

                }

            }

        }

    if ($tEval > 0) {
        if (!Is_cli()) {
            echo '<div class="pbar" id="pbar10">
                      <script type="text/javascript">
                          get_result("10", ' . $tRecs . ', ' . ($tRecs + $delay) . ');
                      </script>
                  </div>';

        }

        if ($i > 0 ) {
            $text = $i . " registos actualizados no SiGES | " . $j . " registos actualizados na BDInt";

            if (!Is_cli()) {
                echo '<script type="text/javascript">
                          show_result("eval", "10", "' . $text . '", ' . ($tRecs + $delay) . ');
                      </script>';

            } else {
                echo $title . ": " . $text . "\n\n";

            }

        } else {
            if (!Is_cli()) {
                echo '<script type="text/javascript">
                          show_result("eval", "10", "sem registos p/ actualizar", ' . ($tRecs + $delay) . ');
                      </script>';

            } else {
                echo $title . ": sem registos p/ actualizar\n\n";

            }

        }

    } else {
        $tRecs = 1000;

        if (!Is_cli()) {
            echo '<div class="pbar" id="pbar10">
                      <script type="text/javascript">
                          get_result("10", ' . $tRecs . ', ' . ($tRecs + $delay) . ');
                      </script>
                  </div>

                  <script type="text/javascript">
                      show_result("eval", "10", "sem registos p/ actualizar", ' . ($tRecs + $delay) . ');
                  </script>';

        } else {
            echo $title . ": sem registos p/ actualizar\n\n";

        }

    }

    if (!Is_cli()) { echo '</div>'; }

$delay += ($tRecs + 3000);

// ABSENTISMO (AF + AC c/ Exame)
    $title = "ABSENTISMO (AF + AC c/ Exame)";

    if (!Is_cli()) {
        echo '<div id="absent">
                  <script type="text/javascript">
                      add_title("absent", "01", "' . $title . '", ' . $delay . ', "darkgrey");
                  </script>';

    }

    // Obter notas por sincronizar na BDInt
        $recs_2sync = array();

        // $get_recs_2sync = "SELECT *
        //                    FROM lead.alunos_inscricoes
        //                    WHERE (((((lead.alunos_inscricoes.MODO_AVA_N = 'C'
        //                              AND lead.alunos_inscricoes.MODO_AVA_R = 'F')
        //                             OR lead.alunos_inscricoes.MODO_AVA_N = 'F') <> FALSE)
        //                            AND lead.alunos_inscricoes.MODO_AVA_R IS NULL)
        //                           AND lead.alunos_inscricoes.NR_AVALIA IS NULL)
        //                        AND (lead.alunos_inscricoes.STATUS_AVALIA = 'M'
        //                            OR lead.alunos_inscricoes.STATUS_AVALIA IS NULL)
        //                        AND CD_STATUS IN (1, 3)
        //                        AND TURMA_MOODLE LIKE '\___\___%'
        //                        AND lead.alunos_inscricoes.CD_LECTIVO >= '" . $lectyear . "'
        //                    ORDER BY RAND()
        //                    LIMIT 100";

        $get_recs_2sync = "SELECT *
                           FROM lead.alunos_inscricoes
                           WHERE (((((lead.alunos_inscricoes.MODO_AVA_N = 'C'
                                     AND lead.alunos_inscricoes.MODO_AVA_R = 'F')
                                    OR lead.alunos_inscricoes.MODO_AVA_N = 'F') <> FALSE)
                                   AND lead.alunos_inscricoes.MODO_AVA_R IS NULL)
                                  AND lead.alunos_inscricoes.NR_AVALIA IS NULL)
                               AND (lead.alunos_inscricoes.STATUS_AVALIA = 'M'
                                   OR lead.alunos_inscricoes.STATUS_AVALIA IS NULL)
                               AND CD_STATUS IN (1, 3)
                               AND (STATUS_AVALIA <> 'A'
                                   OR STATUS_AVALIA IS NULL)
                               AND TURMA_MOODLE LIKE '\___\___%'
                               AND lead.alunos_inscricoes.CD_LECTIVO >= '" . $lectyear . "'
                           ORDER BY RAND()
                           LIMIT 100";

        $result = mysqli_query($bdint, $get_recs_2sync)
                      or die('erro na consulta(01) da BDInt: ' . mysqli_error($bdint) .
                              $nl . $nl . $get_recs_2sync);

        if (mysqli_num_rows($result) > 0) {
            $UCs = "";

            while ($row = mysqli_fetch_assoc($result)) {
                $recs_2sync[] = $row;

                if (!strstr($UCs, $row['CD_DISCIP'])) {
                    if ($UCs <> "") {
                        $UCs .= ",";

                    }
                    
                    $UCs .= $row['CD_DISCIP'];
                
                }

            }

            // Obter pautas por lançar no SiGES
                // $open_grdbks = array();

                // $get_open_grdbks = "SELECT T_ALUNOS_PAUTAS.CD_LECTIVO,
                //                            T_ALUNOS_PAUTAS.CD_CURSO,
                //                            T_ALUNOS_PAUTAS.CD_DISCIP,
                //                            T_ALUNOS_PAUTAS.CD_PAUTA
                //                     FROM LND.T_ALUNOS_PAUTAS
                //                         RIGHT JOIN LND.T_PAUTAS ON LND.T_ALUNOS_PAUTAS.CD_PAUTA = LND.T_PAUTAS.CD_PAUTA
                //                     WHERE LND.T_PAUTAS.CD_SITUACAO = 2
                //                         AND LND.T_ALUNOS_PAUTAS.CD_GRU_AVA <> 2
                //                         AND LND.T_PAUTAS.DT_IMPORTACAO IS NULL
                //                         AND LND.T_PAUTAS.CD_LECTIVO >= '" . $lectyear . "'
                //                         AND LND.T_PAUTAS.CD_DISCIP IN (" . $UCs . ")
                //                     GROUP BY T_ALUNOS_PAUTAS.CD_LECTIVO , T_ALUNOS_PAUTAS.CD_CURSO , T_ALUNOS_PAUTAS.CD_DISCIP , T_ALUNOS_PAUTAS.CD_PAUTA
                //                     ORDER BY T_ALUNOS_PAUTAS.CD_LECTIVO , T_ALUNOS_PAUTAS.CD_CURSO , T_ALUNOS_PAUTAS.CD_DISCIP , T_ALUNOS_PAUTAS.CD_PAUTA";

                // $stmt = oci_parse($siges, $get_open_grdbks);

                // if (!$stmt) {
                //     $error = oci_error($siges);

                //     echo "erro de analise(01): " . $error['message'] .
                //           $nl . $nl . $get_open_grdbks;

                //     exit;

                // }

                // $result = oci_execute($stmt);

                // if (!$result) {
                //     $error = oci_error($stmt);

                //     echo "erro na consulta(01) do SiGES: " . $error['message'] .
                //           $nl . $nl . $get_open_grdbks;

                //     exit;

                // }

                // while (($row = oci_fetch_array($stmt, OCI_ASSOC+OCI_RETURN_NULLS)) != false) {
                //     $open_grdbks[] = $row;

                // }

                // oci_free_statement($stmt);

            // $tRecs = count($recs_2sync) * count($open_grdbks);
            $tRecs = count($recs_2sync);

            if ($tRecs == 0) { $tRecs = 1000; }

            $i = 0;
            $j = 0;

            if (!Is_cli()) {
                echo '<div class="pbar" id="pbar01">
                          <script type="text/javascript">
                              get_result("01", ' . $tRecs . ', ' . ($tRecs + $delay) . ');
                          </script>
                      </div>';

            }

            // if (!empty($open_grdbks) > 0) {
                // Percorrer o array de notas a sincronizar
                foreach ($recs_2sync as $rec_2sync) {
                    // Percorrer o array de pautas por lançar no SiGES
                    // foreach ($open_grdbks as $open_grdbk) {
                         // actualiza SiGES
                    //         $updt_siges = "UPDATE LND.T_ALUNOS_PAUTAS
                    //                        SET NR_NOTA = NULL, ALT_PELO_DOC = 'S'
                    //                        WHERE LND.T_ALUNOS_PAUTAS.CD_LECTIVO = " . $rec_2sync['CD_LECTIVO'] .
                    //                          " AND LND.T_ALUNOS_PAUTAS.CD_CURSO = " . $rec_2sync['CD_CURSO'] .
                    //                          " AND LND.T_ALUNOS_PAUTAS.CD_DISCIP = " . $rec_2sync['CD_DISCIP'] .
                    //                          " AND LND.T_ALUNOS_PAUTAS.CD_PAUTA = " . $open_grdbk['CD_PAUTA'] .
                    //                          " AND LND.T_ALUNOS_PAUTAS.CD_ALUNO = " . $rec_2sync['CD_ALUNO'] .
                    //                          " AND ((('" . $rec_2sync['MODO_AVA_N'] . "' = 'C'
                    //                                AND '" . $rec_2sync['MODO_AVA_R'] . "' = 'F')
                    //                                OR '" . $rec_2sync['MODO_AVA_N'] . "' = 'F')
                    //                            AND '" . $rec_2sync['NR_AVALIA'] . "' IS NULL)
                    //                            AND '" . $rec_2sync['STATUS_AVALIA'] . "' = 'M'";

                    //         $stmt = oci_parse($siges, $updt_siges);

                    //         if (!$stmt) {
                    //             $error = oci_error($siges);

                    //             echo "erro de analise(02): " . $error['message'] .
                    //                   $nl . $nl . $updt_siges;

                    //             exit;

                    //         }

                    //         $result = oci_execute($stmt, OCI_COMMIT_ON_SUCCESS);

                    //         if (!$result) {
                    //             $error = oci_error($stmt);

                    //             echo "erro na actualização(01) do SiGES: " . $error['message'] .
                    //                   $nl . $nl . $updt_siges;

                    //             exit;

                    //         }

                    //         if (oci_num_rows($stmt) > 0) { $i++; }

                    //         oci_free_statement($stmt);

                    // }

                    // actualiza BDInt
                        // $updt_bdint = "UPDATE lead.alunos_inscricoes
                        //                SET MODO_AVA_R = 'F'
                        //                WHERE lead.alunos_inscricoes.CD_LECTIVO = " . $rec_2sync['CD_LECTIVO'] .
                        //                  " AND lead.alunos_inscricoes.CD_CURSO = " . $rec_2sync['CD_CURSO'] .
                        //                  " AND lead.alunos_inscricoes.CD_DISCIP = " . $rec_2sync['CD_DISCIP'] .
                        //                  " AND lead.alunos_inscricoes.CD_ALUNO = " . $rec_2sync['CD_ALUNO'] .
                        //                  " AND ((('" . $rec_2sync['MODO_AVA_N'] . "' = 'C'
                        //                        AND '" . $rec_2sync['MODO_AVA_R'] . "' = 'F')
                        //                        OR '" . $rec_2sync['MODO_AVA_N'] . "' = 'F')
                        //                    AND '" . $rec_2sync['NR_AVALIA'] . "' IS NULL)
                        //                    AND '" . $rec_2sync['STATUS_AVALIA'] . "' = 'M'";

                        $updt_bdint = "UPDATE lead.alunos_inscricoes
                                       SET MODO_AVA_R = 'F'
                                       WHERE lead.alunos_inscricoes.CD_LECTIVO = " . $rec_2sync['CD_LECTIVO'] .
                                         " AND lead.alunos_inscricoes.CD_CURSO = " . $rec_2sync['CD_CURSO'] .
                                         " AND lead.alunos_inscricoes.CD_DISCIP = " . $rec_2sync['CD_DISCIP'] .
                                         " AND lead.alunos_inscricoes.CD_ALUNO = " . $rec_2sync['CD_ALUNO'] .
                                         " AND ((('" . $rec_2sync['MODO_AVA_N'] . "' = 'C'
                                               AND '" . $rec_2sync['MODO_AVA_R'] . "' = 'F')
                                               OR '" . $rec_2sync['MODO_AVA_N'] . "' = 'F')
                                           AND NR_AVALIA IS NULL)";

                        mysqli_query($bdint, $updt_bdint)
                            or die('erro na actualização(01) da BDInt: ' . mysqli_error($bdint) .
                                    $nl . $nl . $updt_bdint);

                        if (mysqli_affected_rows($bdint) > 0) { $j++; }

                }

            // }

            // if ($i > 0 ) {
            if ($j > 0 ) {
                // $text = $i . " registos actualizados no SiGES | " . $j . " registos actualizados na BDInt";
                $text = $j . " registos actualizados na BDInt";

                if (!Is_cli()) {
                    echo '<script type="text/javascript">
                              show_result("absent", "01", "' . $text . '", ' . ($tRecs + $delay) . ');
                          </script>';

                } else {
                    echo $title . ": " . $text . "\n\n";

                }

            } else {
                if (!Is_cli()) {
                    echo '<script type="text/javascript">
                              show_result("absent", "01", "sem registos p/ actualizar", ' . ($tRecs + $delay) . ');
                          </script>';
    
                } else {
                    echo $title . ": sem registos p/ actualizar\n\n";
    
                }
    
            }

        } else {
            $tRecs = 1000;

            if (!Is_cli()) {
                echo '<div class="pbar" id="pbar01">
                          <script type="text/javascript">
                              get_result("01", ' . $tRecs . ', ' . ($tRecs + $delay) . ');
                          </script>
                      </div>

                      <script type="text/javascript">
                          show_result("absent", "01", "sem registos p/ actualizar", ' . ($tRecs + $delay) . ');
                      </script>';

            } else {
                echo $title . ": sem registos p/ actualizar\n\n";

            }

        }

    if (!Is_cli()) { echo '</div>'; }

$delay += ($tRecs + 3000);

// REPROVADOS (AF + AC c/ Exame)
    $title = "REPROVADOS (AF + AC c/ Exame)";

    if (!Is_cli()) {
        echo '<div id="rep_ACAF">
                  <script type="text/javascript">
                      add_title("rep_ACAF", "02", "' . $title . '", ' . $delay . ', "crimson");
                  </script>';

    }

    // Obter notas por sincronizar na BDInt
        $recs_2sync = array();

        $get_recs_2sync = "SELECT *
                           FROM lead.alunos_inscricoes
                           WHERE ((((lead.alunos_inscricoes.MODO_AVA_N = 'C'
                                    AND lead.alunos_inscricoes.MODO_AVA_R = 'F')
                                   OR lead.alunos_inscricoes.MODO_AVA_N = 'F') <> FALSE)
                                AND (lead.alunos_inscricoes.NR_AVALIA >= 0
                                   AND lead.alunos_inscricoes.NR_AVALIA < 10))
                               /* AND lead.alunos_inscricoes.TURMA_MOODLE <> '%AMPV' */
                               AND lead.alunos_inscricoes.STATUS_AVALIA = 'M'
                               AND lead.alunos_inscricoes.CD_LECTIVO >= '" . $lectyear . "'
                           ORDER BY RAND()
                           LIMIT 100";

        $result = mysqli_query($bdint, $get_recs_2sync)
                or die('erro na consulta(02) da BDInt: ' . mysqli_error($bdint) .
                        $nl . $nl . $get_recs_2sync);

        if (mysqli_num_rows($result) > 0) {
            $UCs = "";

            while ($row = mysqli_fetch_assoc($result)) {
                $recs_2sync[] = $row;

                if (!strstr($UCs, $row['CD_DISCIP'])) {
                    if ($UCs <> "") {
                        $UCs .= ",";

                    }
                    
                    $UCs .= $row['CD_DISCIP'];
                
                }

            }

            // Obter pautas por lançar no SiGES
                $open_grdbks = array();

                $get_open_grdbks = "SELECT T_ALUNOS_PAUTAS.CD_LECTIVO,
                                           T_ALUNOS_PAUTAS.CD_CURSO,
                                           T_ALUNOS_PAUTAS.CD_DISCIP,
                                           T_ALUNOS_PAUTAS.CD_PAUTA
                                    FROM LND.T_ALUNOS_PAUTAS
                                        RIGHT JOIN LND.T_PAUTAS ON LND.T_ALUNOS_PAUTAS.CD_PAUTA = LND.T_PAUTAS.CD_PAUTA
                                    WHERE LND.T_PAUTAS.CD_SITUACAO = 2
                                        AND LND.T_ALUNOS_PAUTAS.CD_GRU_AVA <> 3
                                        AND LND.T_PAUTAS.DT_IMPORTACAO IS NULL
                                        AND LND.T_PAUTAS.CD_LECTIVO >= '" . $lectyear . "'
                                        AND LND.T_PAUTAS.CD_DISCIP IN (" . $UCs . ")
                                    GROUP BY T_ALUNOS_PAUTAS.CD_LECTIVO , T_ALUNOS_PAUTAS.CD_CURSO , T_ALUNOS_PAUTAS.CD_DISCIP , T_ALUNOS_PAUTAS.CD_PAUTA
                                    ORDER BY T_ALUNOS_PAUTAS.CD_LECTIVO , T_ALUNOS_PAUTAS.CD_CURSO , T_ALUNOS_PAUTAS.CD_DISCIP , T_ALUNOS_PAUTAS.CD_PAUTA";

                $stmt = oci_parse($siges, $get_open_grdbks);

                if (!$stmt) {
                    $error = oci_error($siges);

                    echo "erro de analise(03): " . $error['message'] .
                          $nl . $nl . $get_open_grdbks;

                    exit;

                }

                $result = oci_execute($stmt);

                if (!$result) {
                    $error = oci_error($stmt);

                    echo "erro na consulta(02) do SiGES: " . $error['message'] .
                          $nl . $nl . $get_open_grdbks;

                    exit;

                }

                while (($row = oci_fetch_array($stmt, OCI_ASSOC+OCI_RETURN_NULLS)) != false) {
                    $open_grdbks[] = $row;

                }

                oci_free_statement($stmt);
            
            $tRecs = count($recs_2sync) * count($open_grdbks);

            if ($tRecs == 0) { $tRecs = 1000; }

            $i = 0;
            $j = 0;

            if (!Is_cli()) {
                echo '<div class="pbar" id="pbar02">
                          <script type="text/javascript">
                              get_result("02", ' . $tRecs . ', ' . ($tRecs + $delay) . ');
                          </script>
                      </div>';

            }

            if (!empty($open_grdbks) > 0) {
                // Percorrer o array de notas a sincronizar
                foreach ($recs_2sync as $rec_2sync) {
                    // Percorrer o array de pautas por lançar no SiGES
                    foreach ($open_grdbks as $open_grdbk) {
                        // actualiza SiGES
                            $updt_siges = "UPDATE LND.T_ALUNOS_PAUTAS
                                           SET NR_NOTA = " . $rec_2sync['NR_AVALIA'] . ", ALT_PELO_DOC = 'S'
                                           WHERE LND.T_ALUNOS_PAUTAS.CD_LECTIVO = " . $rec_2sync['CD_LECTIVO'] .
                                             " AND LND.T_ALUNOS_PAUTAS.CD_CURSO = " . $rec_2sync['CD_CURSO'] .
                                             " AND LND.T_ALUNOS_PAUTAS.CD_DISCIP = " . $rec_2sync['CD_DISCIP'] .
                                             " AND LND.T_ALUNOS_PAUTAS.CD_PAUTA = " . $open_grdbk['CD_PAUTA'] .
                                             " AND LND.T_ALUNOS_PAUTAS.CD_ALUNO = " . $rec_2sync['CD_ALUNO'] .
                                             " AND ((('" . $rec_2sync['MODO_AVA_N'] . "' = 'C'
                                                   AND '" . $rec_2sync['MODO_AVA_R'] . "' = 'F')
                                                   OR '" . $rec_2sync['MODO_AVA_N'] . "' = 'F')
                                               AND (" . $rec_2sync['NR_AVALIA'] . " >= 0
                                                   AND " . $rec_2sync['NR_AVALIA'] . " < 10))
                                               /* AND '" . $rec_2sync['TURMA_MOODLE'] . "' <> '%AMPV' */
                                               AND '" . $rec_2sync['STATUS_AVALIA'] . "' = 'M'";

                            $stmt = oci_parse($siges, $updt_siges);

                            if (!$stmt) {
                                $error = oci_error($siges);

                                echo "erro de analise(04): " . $error['message'] .
                                      $nl . $nl . $updt_siges;

                                exit;

                            }

                            $result = oci_execute($stmt, OCI_COMMIT_ON_SUCCESS);

                            if (!$result) {
                                $error = oci_error($stmt);

                                echo "erro na actualização(02) do SiGES: " . $error['message'] .
                                      $nl . $nl . $updt_siges;

                                exit;

                            }

                            if (oci_num_rows($stmt) > 0) {
                                $i++;
                            
                                // actualiza BDInt
                                    $updt_bdint = "UPDATE lead.alunos_inscricoes
                                                   SET STATUS_AVALIA = 'A'
                                                   WHERE lead.alunos_inscricoes.CD_LECTIVO = " . $rec_2sync['CD_LECTIVO'] .
                                                     " AND lead.alunos_inscricoes.CD_CURSO = " . $rec_2sync['CD_CURSO'] .
                                                     " AND lead.alunos_inscricoes.CD_DISCIP = " . $rec_2sync['CD_DISCIP'] .
                                                     " AND lead.alunos_inscricoes.CD_ALUNO = " . $rec_2sync['CD_ALUNO'] .
                                                     " AND ((('" . $rec_2sync['MODO_AVA_N'] . "' = 'C'
                                                           AND '" . $rec_2sync['MODO_AVA_R'] . "' = 'F')
                                                           OR '" . $rec_2sync['MODO_AVA_N'] . "' = 'F')
                                                       AND (" . $rec_2sync['NR_AVALIA'] . " >= 0
                                                           AND " . $rec_2sync['NR_AVALIA'] . " < 10))
                                                       /* AND '" . $rec_2sync['TURMA_MOODLE'] . "' <> '%AMPV' */
                                                       AND '" . $rec_2sync['STATUS_AVALIA'] . "' = 'M'";

                                    mysqli_query($bdint, $updt_bdint)
                                        or die('erro na actualização(02) da BDInt: ' . mysqli_error($bdint) .
                                                $nl . $nl . $updt_bdint);

                                    if (mysqli_affected_rows($bdint) > 0) { $j++; }

                            }

                            oci_free_statement($stmt);

                    }

                }

            }

            if ($i > 0 ) {
                $text = $i . " registos actualizados no SiGES | " . $j . " registos actualizados na BDInt";

                if (!Is_cli()) {
                    echo '<script type="text/javascript">
                              show_result("rep_ACAF", "02", "' . $text . '", ' . ($tRecs + $delay) . ');
                          </script>';

                } else {
                    echo $title . ": " . $text . "\n\n";

                }

            } else {
                if (!Is_cli()) {
                    echo '<script type="text/javascript">
                              show_result("rep_ACAF", "02", "sem registos p/ actualizar", ' . ($tRecs + $delay) . ');
                          </script>';
    
                } else {
                    echo $title . ": sem registos p/ actualizar\n\n";
    
                }
    
            }

        } else {
            $tRecs = 1000;

            if (!Is_cli()) {
                echo '<div class="pbar" id="pbar02">
                          <script type="text/javascript">
                              get_result("02", ' . $tRecs . ', ' . ($tRecs + $delay) . ');
                          </script>
                      </div>

                      <script type="text/javascript">
                          show_result("rep_ACAF", "02", "sem registos p/ actualizar", ' . ($tRecs + $delay) . ');
                      </script>';

            } else {
                echo $title . ": sem registos p/ actualizar\n\n";

            }

        }

    if (!Is_cli()) { echo '</div>'; }

$delay += ($tRecs + 3000);

// REPROVADOS (AC)
    $title = "REPROVADOS (AC)";

    if (!Is_cli()) {
        echo '<div id="rep_AC">
                  <script type="text/javascript">
                      add_title("rep_AC", "03", "' . $title . '", ' . $delay . ', "crimson");
                  </script>';

    }

    // Obter notas por sincronizar na BDInt
        $recs_2sync = array();

        $get_recs_2sync = "SELECT *
                           FROM lead.alunos_inscricoes
                           WHERE (lead.alunos_inscricoes.MODO_AVA_N = 'C'
                                AND lead.alunos_inscricoes.NR_AVALIA = 0)
                               AND lead.alunos_inscricoes.STATUS_AVALIA = 'M'
                               AND lead.alunos_inscricoes.CD_LECTIVO >= '" . $lectyear . "'
                           ORDER BY RAND()
                           LIMIT 100";

        $result = mysqli_query($bdint, $get_recs_2sync)
                or die('erro na consulta(03) da BDInt: ' . mysqli_error($bdint) .
                        $nl . $nl . $get_recs_2sync);

        if (mysqli_num_rows($result) > 0) {
            $UCs = "";

            while ($row = mysqli_fetch_assoc($result)) {
                $recs_2sync[] = $row;

                if (!strstr($UCs, $row['CD_DISCIP'])) {
                    if ($UCs <> "") {
                        $UCs .= ",";

                    }
                    
                    $UCs .= $row['CD_DISCIP'];
                
                }

            }

            // Obter pautas por lançar no SiGES
                $open_grdbks = array();

                $get_open_grdbks = "SELECT T_ALUNOS_PAUTAS.CD_LECTIVO,
                                           T_ALUNOS_PAUTAS.CD_CURSO,
                                           T_ALUNOS_PAUTAS.CD_DISCIP,
                                           T_ALUNOS_PAUTAS.CD_PAUTA
                                    FROM LND.T_ALUNOS_PAUTAS
                                        RIGHT JOIN LND.T_PAUTAS ON LND.T_ALUNOS_PAUTAS.CD_PAUTA = LND.T_PAUTAS.CD_PAUTA
                                    WHERE LND.T_PAUTAS.CD_SITUACAO = 2
                                        AND LND.T_ALUNOS_PAUTAS.CD_GRU_AVA <> 2
                                        AND LND.T_PAUTAS.DT_IMPORTACAO IS NULL
                                        AND LND.T_PAUTAS.CD_LECTIVO >= '" . $lectyear . "'
                                        AND LND.T_PAUTAS.CD_DISCIP IN (" . $UCs . ")
                                    GROUP BY T_ALUNOS_PAUTAS.CD_LECTIVO , T_ALUNOS_PAUTAS.CD_CURSO , T_ALUNOS_PAUTAS.CD_DISCIP , T_ALUNOS_PAUTAS.CD_PAUTA
                                    ORDER BY T_ALUNOS_PAUTAS.CD_LECTIVO , T_ALUNOS_PAUTAS.CD_CURSO , T_ALUNOS_PAUTAS.CD_DISCIP , T_ALUNOS_PAUTAS.CD_PAUTA";

                $stmt = oci_parse($siges, $get_open_grdbks);

                if (!$stmt) {
                    $error = oci_error($siges);

                    echo "erro de analise(05): " . $error['message'] .
                          $nl . $nl . $get_open_grdbks;

                    exit;

                }

                $result = oci_execute($stmt);

                if (!$result) {
                    $error = oci_error($stmt);

                    echo "erro na consulta(03) do SiGES: " . $error['message'] .
                          $nl . $nl . $get_open_grdbks;

                    exit;

                }

                while (($row = oci_fetch_array($stmt, OCI_ASSOC+OCI_RETURN_NULLS)) != false) {
                    $open_grdbks[] = $row;

                }

                oci_free_statement($stmt);

            $tRecs = count($recs_2sync) * count($open_grdbks);

            if ($tRecs == 0) { $tRecs = 1000; }

            $i = 0;
            $j = 0;

            if (!Is_cli()) {
                echo '<div class="pbar" id="pbar03">
                          <script type="text/javascript">
                              get_result("03", ' . $tRecs . ', ' . ($tRecs + $delay) . ');
                          </script>
                      </div>';

            }

            if (!empty($open_grdbks) > 0) {
                // Percorrer o array de notas a sincronizar
                foreach ($recs_2sync as $rec_2sync) {
                    // Percorrer o array de pautas por lançar no SiGES
                    foreach ($open_grdbks as $open_grdbk) {
                        // actualiza SiGES
                            $updt_siges = "UPDATE LND.T_ALUNOS_PAUTAS
                                           SET CD_STA_EPO = 4, ALT_PELO_DOC = 'S'
                                           WHERE LND.T_ALUNOS_PAUTAS.CD_LECTIVO = " . $rec_2sync['CD_LECTIVO'] .
                                             " AND LND.T_ALUNOS_PAUTAS.CD_CURSO = " . $rec_2sync['CD_CURSO'] .
                                             " AND LND.T_ALUNOS_PAUTAS.CD_DISCIP = " . $rec_2sync['CD_DISCIP'] .
                                             " AND LND.T_ALUNOS_PAUTAS.CD_PAUTA = " . $open_grdbk['CD_PAUTA'] .
                                             " AND LND.T_ALUNOS_PAUTAS.CD_ALUNO = " . $rec_2sync['CD_ALUNO'] .
                                             " AND ('" . $rec_2sync['MODO_AVA_N'] . "' = 'C'
                                               AND " . $rec_2sync['NR_AVALIA'] . " = 0)
                                               AND '" . $rec_2sync['STATUS_AVALIA'] . "' = 'M'";

                            $stmt = oci_parse($siges, $updt_siges);

                            if (!$stmt) {
                                $error = oci_error($siges);

                                echo "erro de analise(06): " . $error['message'] .
                                      $nl . $nl . $updt_siges;

                                exit;

                            }

                            $result = oci_execute($stmt, OCI_COMMIT_ON_SUCCESS);

                            if (!$result) {
                                $error = oci_error($stmt);

                                echo "erro na actualização(03) do SiGES: " . $error['message'] .
                                      $nl . $nl . $updt_siges;

                                exit;

                            }

                            if (oci_num_rows($stmt) > 0) {
                                $i++;
                            
                                // actualiza BDInt
                                    $updt_bdint = "UPDATE lead.alunos_inscricoes
                                                   SET STATUS_AVALIA = 'A'
                                                   WHERE lead.alunos_inscricoes.CD_LECTIVO = " . $rec_2sync['CD_LECTIVO'] .
                                                     " AND lead.alunos_inscricoes.CD_CURSO = " . $rec_2sync['CD_CURSO'] .
                                                     " AND lead.alunos_inscricoes.CD_DISCIP = " . $rec_2sync['CD_DISCIP'] .
                                                     " AND lead.alunos_inscricoes.CD_ALUNO = " . $rec_2sync['CD_ALUNO'] .
                                                     " AND ('" . $rec_2sync['MODO_AVA_N'] . "' = 'C'
                                                       AND " . $rec_2sync['NR_AVALIA'] . " = 0)
                                                       AND '" . $rec_2sync['STATUS_AVALIA'] . "' = 'M'";

                                    mysqli_query($bdint, $updt_bdint)
                                        or die('erro na actualização(03) da BDInt: ' . mysqli_error($bdint) .
                                                $nl . $nl . $updt_bdint);

                                    if (mysqli_affected_rows($bdint) > 0) { $j++; }

                            }

                            oci_free_statement($stmt);

                    }

                }

            }
            
            if ($i > 0 ) {
                $text = $i . " registos actualizados no SiGES | " . $j . " registos actualizados na BDInt";

                if (!Is_cli()) {
                    echo '<script type="text/javascript">
                              show_result("rep_AC", "03", "' . $text . '", ' . ($tRecs + $delay) . ');
                          </script>';

                } else {
                    echo $title . ": " . $text . "\n\n";

                }

            } else {
                if (!Is_cli()) {
                    echo '<script type="text/javascript">
                              show_result("rep_AC", "03", "sem registos p/ actualizar", ' . ($tRecs + $delay) . ');
                          </script>';
    
                } else {
                    echo $title . ": sem registos p/ actualizar\n\n";
    
                }

            }

        } else {
            $tRecs = 1000;

            if (!Is_cli()) {
                echo '<div class="pbar" id="pbar03">
                            <script type="text/javascript">
                                get_result("03", ' . $tRecs . ', ' . ($tRecs + $delay) . ');
                            </script>
                        </div>

                        <script type="text/javascript">
                            show_result("rep_AC", "03", "sem registos p/ actualizar", ' . ($tRecs + $delay) . ');
                        </script>';

            } else {
                echo $title . ": sem registos p/ actualizar\n\n";

            }

        }

    if (!Is_cli()) { echo '</div>'; }

$delay += ($tRecs + 3000);

// REPROVADOS (AC N/A)
    $title = "REPROVADOS (AC N/A)";

    if (!Is_cli()) {
        echo '<div id="NA">
                  <script type="text/javascript">
                      add_title("NA", "04", "' . $title . '", ' . $delay . ', "crimson");
                  </script>';

    }

    // Obter notas por sincronizar na BDInt
        $recs_2sync = array();

        $get_recs_2sync = "SELECT *
                           FROM lead.alunos_inscricoes
                           WHERE (lead.alunos_inscricoes.MODO_AVA_N = 'C'
                                AND lead.alunos_inscricoes.NR_AVALIA = 1)
                               AND lead.alunos_inscricoes.STATUS_AVALIA = 'M'
                               AND lead.alunos_inscricoes.CD_LECTIVO >= '" . $lectyear . "'
                           ORDER BY RAND()
                           LIMIT 100";

        $result = mysqli_query($bdint, $get_recs_2sync)
                or die('erro na consulta(04) da BDInt: ' . mysqli_error($bdint) .
                        $nl . $nl . $get_recs_2sync);

        if (mysqli_num_rows($result) > 0) {
            $UCs = "";

            while ($row = mysqli_fetch_assoc($result)) {
                $recs_2sync[] = $row;

                if (!strstr($UCs, $row['CD_DISCIP'])) {
                    if ($UCs <> "") {
                        $UCs .= ",";

                    }
                    
                    $UCs .= $row['CD_DISCIP'];
                
                }

            }

            // Obter pautas por lançar no SiGES
                $open_grdbks = array();

                $get_open_grdbks = "SELECT T_ALUNOS_PAUTAS.CD_LECTIVO,
                                           T_ALUNOS_PAUTAS.CD_CURSO,
                                           T_ALUNOS_PAUTAS.CD_DISCIP,
                                           T_ALUNOS_PAUTAS.CD_PAUTA
                                    FROM LND.T_ALUNOS_PAUTAS
                                        RIGHT JOIN LND.T_PAUTAS ON LND.T_ALUNOS_PAUTAS.CD_PAUTA = LND.T_PAUTAS.CD_PAUTA
                                    WHERE LND.T_PAUTAS.CD_SITUACAO = 2
                                        AND LND.T_ALUNOS_PAUTAS.CD_GRU_AVA <> 2
                                        AND LND.T_PAUTAS.DT_IMPORTACAO IS NULL
                                        AND LND.T_PAUTAS.CD_LECTIVO >= '" . $lectyear . "'
                                        AND LND.T_PAUTAS.CD_DISCIP IN (" . $UCs . ")
                                    GROUP BY T_ALUNOS_PAUTAS.CD_LECTIVO , T_ALUNOS_PAUTAS.CD_CURSO , T_ALUNOS_PAUTAS.CD_DISCIP , T_ALUNOS_PAUTAS.CD_PAUTA
                                    ORDER BY T_ALUNOS_PAUTAS.CD_LECTIVO , T_ALUNOS_PAUTAS.CD_CURSO , T_ALUNOS_PAUTAS.CD_DISCIP , T_ALUNOS_PAUTAS.CD_PAUTA";

                $stmt = oci_parse($siges, $get_open_grdbks);

                if (!$stmt) {
                    $error = oci_error($siges);

                    echo "erro de analise(07): " . $error['message'] .
                          $nl . $nl . $get_open_grdbks;

                    exit;

                }

                $result = oci_execute($stmt);

                if (!$result) {
                    $error = oci_error($stmt);

                    echo "erro na consulta(04) do SiGES: " . $error['message'] .
                          $nl . $nl . $get_open_grdbks;

                    exit;

                }

                while (($row = oci_fetch_array($stmt, OCI_ASSOC+OCI_RETURN_NULLS)) != false) {
                    $open_grdbks[] = $row;

                }

                oci_free_statement($stmt);

            $tRecs = count($recs_2sync) * count($open_grdbks);

            if ($tRecs == 0) { $tRecs = 1000; }

            $i = 0;
            $j = 0;

            if (!Is_cli()) {
                echo '<div class="pbar" id="pbar04">
                          <script type="text/javascript">
                              get_result("04", ' . $tRecs . ', ' . ($tRecs + $delay) . ');
                          </script>
                      </div>';

            }

            if (!empty($open_grdbks) > 0) {
                // Percorrer o array de notas a sincronizar
                foreach ($recs_2sync as $rec_2sync) {
                    // Percorrer o array de pautas por lançar no SiGES
                    foreach ($open_grdbks as $open_grdbk) {
                        // actualiza SiGES
                            $updt_siges = "UPDATE LND.T_ALUNOS_PAUTAS
                                           SET CD_STA_EPO = 16, ALT_PELO_DOC = 'S'
                                           WHERE LND.T_ALUNOS_PAUTAS.CD_LECTIVO = " . $rec_2sync['CD_LECTIVO'] .
                                             " AND LND.T_ALUNOS_PAUTAS.CD_CURSO = " . $rec_2sync['CD_CURSO'] .
                                             " AND LND.T_ALUNOS_PAUTAS.CD_DISCIP = " . $rec_2sync['CD_DISCIP'] .
                                             " AND LND.T_ALUNOS_PAUTAS.CD_PAUTA = " . $open_grdbk['CD_PAUTA'] .
                                             " AND LND.T_ALUNOS_PAUTAS.CD_ALUNO = " . $rec_2sync['CD_ALUNO'] .
                                             " AND ('" . $rec_2sync['MODO_AVA_N'] . "' = 'C'
                                               AND " . $rec_2sync['NR_AVALIA'] . " = 1)
                                               AND '" . $rec_2sync['STATUS_AVALIA'] . "' = 'M'";

                            $stmt = oci_parse($siges, $updt_siges);

                            if (!$stmt) {
                                $error = oci_error($siges);

                                echo "erro de analise(08): " . $error['message'] .
                                      $nl . $nl . $updt_siges;

                                exit;

                            }

                            $result = oci_execute($stmt, OCI_COMMIT_ON_SUCCESS);

                            if (!$result) {
                                $error = oci_error($stmt);

                                echo "erro na actualização(04) do SiGES: " . $error['message'] .
                                      $nl . $nl . $updt_siges;

                                exit;

                            }

                            if (oci_num_rows($stmt) > 0) {
                                $i++;
                            
                                // actualiza BDInt
                                    $updt_bdint = "UPDATE lead.alunos_inscricoes
                                                   SET STATUS_AVALIA = 'A'
                                                   WHERE lead.alunos_inscricoes.CD_LECTIVO = " . $rec_2sync['CD_LECTIVO'] .
                                                     " AND lead.alunos_inscricoes.CD_CURSO = " . $rec_2sync['CD_CURSO'] .
                                                     " AND lead.alunos_inscricoes.CD_DISCIP = " . $rec_2sync['CD_DISCIP'] .
                                                     " AND lead.alunos_inscricoes.CD_ALUNO = " . $rec_2sync['CD_ALUNO'] .
                                                     " AND ('" . $rec_2sync['MODO_AVA_N'] . "' = 'C'
                                                       AND " . $rec_2sync['NR_AVALIA'] . " = 1)
                                                       AND '" . $rec_2sync['STATUS_AVALIA'] . "' = 'M'";

                                    mysqli_query($bdint, $updt_bdint)
                                        or die('erro na actualização(04) da BDInt: ' . mysqli_error($bdint) .
                                                $nl . $nl . $updt_bdint);

                                    if (mysqli_affected_rows($bdint) > 0) { $j++; }

                            }

                            oci_free_statement($stmt);

                    }

                }

            }

            if ($i > 0 ) {
                $text = $i . " registos actualizados no SiGES | " . $j . " registos actualizados na BDInt";

                if (!Is_cli()) {
                    echo '<script type="text/javascript">
                              show_result("NA", "04", "' . $text . '", ' . ($tRecs + $delay) . ');
                          </script>';

                } else {
                    echo $title . ": " . $text . "\n\n";

                }
            
            } else {
                if (!Is_cli()) {
                    echo '<script type="text/javascript">
                              show_result("NA", "04", "sem registos p/ actualizar", ' . ($tRecs + $delay) . ');
                          </script>';
    
                } else {
                    echo $title . ": sem registos p/ actualizar\n\n";
                }

            }

        } else {
            $tRecs = 1000;

            if (!Is_cli()) {
                echo '<div class="pbar" id="pbar04">
                          <script type="text/javascript">
                              get_result("04", ' . $tRecs . ', ' . ($tRecs + $delay) . ');
                          </script>
                      </div>

                      <script type="text/javascript">
                          show_result("NA", "04", "sem registos p/ actualizar", ' . ($tRecs + $delay) . ');
                      </script>';

            } else {
                echo $title . ": sem registos p/ actualizar\n\n";

            }

        }

    if (!Is_cli()) { echo '</div>'; }

$delay += ($tRecs + 3000);

// APROVADOS (AC + AF)
    $title = "APROVADOS (AC + AF)";

    if (!Is_cli()) {
        echo '<div id="aprov">
                  <script type="text/javascript">
                      add_title("aprov", "05", "' . $title . '", ' . $delay . ', "green");
                  </script>';

    }

    // Obter notas por sincronizar na BDInt
        $recs_2sync = array();

        $get_recs_2sync = "SELECT *
                           FROM lead.alunos_inscricoes
                           WHERE ((lead.alunos_inscricoes.MODO_AVA_N = 'C'
                                   OR lead.alunos_inscricoes.MODO_AVA_N = 'F')
                                  AND lead.alunos_inscricoes.NR_AVALIA >= 10)
                               /* AND lead.alunos_inscricoes.TURMA_MOODLE <> '%AMPV' */
                               AND lead.alunos_inscricoes.STATUS_AVALIA = 'M'
                               AND lead.alunos_inscricoes.CD_LECTIVO >= '" . $lectyear . "'
                           ORDER BY RAND()
                           LIMIT 100";

        $result = mysqli_query($bdint, $get_recs_2sync)
                or die('erro na consulta(05) da BDInt: ' . mysqli_error($bdint) .
                        $nl . $nl . $get_recs_2sync);

        if (mysqli_num_rows($result) > 0) {
            $UCs = "";

            while ($row = mysqli_fetch_assoc($result)) {
                $recs_2sync[] = $row;

                if (!strstr($UCs, $row['CD_DISCIP'])) {
                    if ($UCs <> "") {
                        $UCs .= ",";

                    }
                    
                    $UCs .= $row['CD_DISCIP'];
                
                }

            }

            // Obter pautas por lançar no SiGES
                $open_grdbks = array();

                $get_open_grdbks = "SELECT T_ALUNOS_PAUTAS.CD_LECTIVO,
                                           T_ALUNOS_PAUTAS.CD_CURSO,
                                           T_ALUNOS_PAUTAS.CD_DISCIP,
                                           T_ALUNOS_PAUTAS.CD_PAUTA
                                    FROM LND.T_ALUNOS_PAUTAS
                                        RIGHT JOIN LND.T_PAUTAS ON LND.T_ALUNOS_PAUTAS.CD_PAUTA = LND.T_PAUTAS.CD_PAUTA
                                    WHERE LND.T_PAUTAS.CD_SITUACAO = 2
                                        AND LND.T_ALUNOS_PAUTAS.CD_GRU_AVA NOT IN (3 , 4, 49)
                                        AND LND.T_PAUTAS.DT_IMPORTACAO IS NULL
                                        AND LND.T_PAUTAS.CD_LECTIVO >= '" . $lectyear . "'
                                        AND LND.T_PAUTAS.CD_DISCIP IN (" . $UCs . ")
                                    GROUP BY T_ALUNOS_PAUTAS.CD_LECTIVO , T_ALUNOS_PAUTAS.CD_CURSO , T_ALUNOS_PAUTAS.CD_DISCIP , T_ALUNOS_PAUTAS.CD_PAUTA
                                    ORDER BY T_ALUNOS_PAUTAS.CD_LECTIVO , T_ALUNOS_PAUTAS.CD_CURSO , T_ALUNOS_PAUTAS.CD_DISCIP , T_ALUNOS_PAUTAS.CD_PAUTA";

                $stmt = oci_parse($siges, $get_open_grdbks);

                if (!$stmt) {
                    $error = oci_error($siges);

                    echo "erro de analise(09): " . $error['message'] .
                          $nl . $nl . $get_open_grdbks;

                    exit;

                }

                $result = oci_execute($stmt);

                if (!$result) {
                    $error = oci_error($stmt);

                    echo "erro na consulta(05) do SiGES: " . $error['message'] .
                          $nl . $nl . $get_open_grdbks;

                    exit;

                }

                while (($row = oci_fetch_array($stmt, OCI_ASSOC+OCI_RETURN_NULLS)) != false) {
                    $open_grdbks[] = $row;

                }

                oci_free_statement($stmt);

            $tRecs = count($recs_2sync) * count($open_grdbks);

            if ($tRecs == 0) { $tRecs = 1000; }

            $i = 0;
            $j = 0;

            if (!Is_cli()) {
                echo '<div class="pbar" id="pbar05">
                          <script type="text/javascript">
                              get_result("05", ' . $tRecs . ', ' . ($tRecs + $delay) . ');
                          </script>
                      </div>';

            }

            if (!empty($open_grdbks) > 0) {
                // Percorrer o array de notas a sincronizar
                foreach ($recs_2sync as $rec_2sync) {
                    // Percorrer o array de pautas por lançar no SiGES
                    foreach ($open_grdbks as $open_grdbk) {
                        // actualiza SiGES
                            $updt_siges = "UPDATE LND.T_ALUNOS_PAUTAS
                                           SET NR_NOTA = " . $rec_2sync['NR_AVALIA'] . ", ALT_PELO_DOC = 'S'
                                           WHERE LND.T_ALUNOS_PAUTAS.CD_LECTIVO = " . $rec_2sync['CD_LECTIVO'] .
                                             " AND LND.T_ALUNOS_PAUTAS.CD_CURSO = " . $rec_2sync['CD_CURSO'] .
                                             " AND LND.T_ALUNOS_PAUTAS.CD_DISCIP = " . $rec_2sync['CD_DISCIP'] .
                                             " AND LND.T_ALUNOS_PAUTAS.CD_PAUTA = " . $open_grdbk['CD_PAUTA'] .
                                             " AND LND.T_ALUNOS_PAUTAS.CD_ALUNO = " . $rec_2sync['CD_ALUNO'] .
                                             " AND (('" . $rec_2sync['MODO_AVA_N'] . "' = 'C'
                                                   OR '" . $rec_2sync['MODO_AVA_N'] . "' = 'F')
                                               AND " . $rec_2sync['NR_AVALIA'] . " >= 10)
                                               /* AND '" . $rec_2sync['TURMA_MOODLE'] . "' <> '%AMPV' */
                                               AND '" . $rec_2sync['STATUS_AVALIA'] . "' = 'M'";

                            $stmt = oci_parse($siges, $updt_siges);

                            if (!$stmt) {
                                $error = oci_error($siges);

                                echo "erro de analise(10): " . $error['message'] .
                                      $nl . $nl . $updt_siges;

                                exit;

                            }

                            $result = oci_execute($stmt, OCI_COMMIT_ON_SUCCESS);

                            if (!$result) {
                                $error = oci_error($stmt);

                                echo "erro na actualização(05) do SiGES: " . $error['message'] .
                                      $nl . $nl . $updt_siges;

                                exit;

                            }

                            if (oci_num_rows($stmt) > 0) {
                                $i++;
                            
                                // actualiza BDInt
                                    $updt_bdint = "UPDATE lead.alunos_inscricoes
                                                   SET STATUS_AVALIA = 'A', MODO_AVA_R = 'A'
                                                   WHERE lead.alunos_inscricoes.CD_LECTIVO = " . $rec_2sync['CD_LECTIVO'] .
                                                     " AND lead.alunos_inscricoes.CD_CURSO = " . $rec_2sync['CD_CURSO'] .
                                                     " AND lead.alunos_inscricoes.CD_DISCIP = " . $rec_2sync['CD_DISCIP'] .
                                                     " AND lead.alunos_inscricoes.CD_ALUNO = " . $rec_2sync['CD_ALUNO'] .
                                                     " AND (('" . $rec_2sync['MODO_AVA_N'] . "' = 'C'
                                                           OR '" . $rec_2sync['MODO_AVA_N'] . "' = 'F')
                                                       AND " . $rec_2sync['NR_AVALIA'] . " >= 10)
                                                       /* AND '" . $rec_2sync['TURMA_MOODLE'] . "' <> '%AMPV' */
                                                       AND '" . $rec_2sync['STATUS_AVALIA'] . "' = 'M'";

                                    mysqli_query($bdint, $updt_bdint)
                                        or die('erro na actualização(05) da BDInt: ' . mysqli_error($bdint) .
                                                $nl . $nl . $updt_bdint);

                                    if (mysqli_affected_rows($bdint) > 0) { $j++; }

                            }

                            oci_free_statement($stmt);

                    }

                }

            }

            if ($i > 0 ) {
                $text = $i . " registos actualizados no SiGES | " . $j . " registos actualizados na BDInt";

                if (!Is_cli()) {
                    echo '<script type="text/javascript">
                              show_result("aprov", "05", "' . $text . '", ' . ($tRecs + $delay) . ');
                          </script>';

                } else {
                    echo $title . ": " . $text . "\n\n";

                }
            } else {
                if (!Is_cli()) {
                    echo '<script type="text/javascript">
                              show_result("aprov", "05", "sem registos p/ actualizar", ' . ($tRecs + $delay) . ');
                          </script>';
    
                } else {
                    echo $title . ": sem registos p/ actualizar\n\n";
    
                }

            }

        } else {
            $tRecs = 1000;

            if (!Is_cli()) {
                echo '<div class="pbar" id="pbar05">
                          <script type="text/javascript">
                              get_result("05", ' . $tRecs . ', ' . ($tRecs + $delay) . ');
                          </script>
                      </div>

                      <script type="text/javascript">
                          show_result("aprov", "05", "sem registos p/ actualizar", ' . ($tRecs + $delay) . ');
                      </script>';

            } else {
                echo $title . ": sem registos p/ actualizar\n\n";

            }

        }

    if (!Is_cli()) { echo '</div>'; }

$delay += ($tRecs + 3000);

// RECURSO (AC)
    $title = "RECURSO (AC)";

    if (!Is_cli()) {
        echo '<div id="rep_AC_rec">
                  <script type="text/javascript">
                      add_title("rep_AC_rec", "06", "' . $title . '", ' . $delay . ', "firebrick");
                  </script>';

    }

    // Obter notas negativas sincronizadas na BDInt
        $recs_2sync = array();

        $get_recs_2sync = "SELECT *
                           FROM lead.alunos_inscricoes
                           WHERE (lead.alunos_inscricoes.MODO_AVA_N = 'C'
                                AND lead.alunos_inscricoes.NR_AVALIA = 0)
                               AND lead.alunos_inscricoes.STATUS_AVALIA = 'A'
                               AND lead.alunos_inscricoes.MODO_AVA_R IS NULL
                               AND lead.alunos_inscricoes.CD_LECTIVO >= '" . $lectyear . "'
                           ORDER BY RAND()
                           LIMIT 100";

        $result = mysqli_query($bdint, $get_recs_2sync)
                or die('erro na consulta(06) da BDInt: ' . mysqli_error($bdint) .
                        $nl . $nl . $get_recs_2sync);

        if (mysqli_num_rows($result) > 0) {
            while ($row = mysqli_fetch_assoc($result)) {
                $recs_2sync[] = $row;

            }

            $tRecs = count($recs_2sync);

            $j = 0;

            if (!Is_cli()) {
                echo '<div class="pbar" id="pbar06">
                          <script type="text/javascript">
                              get_result("06", ' . $tRecs . ', ' . ($tRecs + $delay) . ');
                          </script>
                      </div>';

            }

            // Percorrer o array de registos elegíveis p/ recurso
                foreach ($recs_2sync as $rec_2sync) {
                // actualiza BDInt
                    $updt_bdint = "UPDATE lead.alunos_inscricoes
                                   SET MODO_AVA_R = 'C'
                                   WHERE lead.alunos_inscricoes.CD_LECTIVO = " . $rec_2sync['CD_LECTIVO'] .
                                     " AND lead.alunos_inscricoes.CD_CURSO = " . $rec_2sync['CD_CURSO'] .
                                     " AND lead.alunos_inscricoes.CD_DISCIP = " . $rec_2sync['CD_DISCIP'] .
                                     " AND lead.alunos_inscricoes.CD_ALUNO = " . $rec_2sync['CD_ALUNO'] .
                                     " AND ('" . $rec_2sync['MODO_AVA_N'] . "' = 'C'
                                       AND " . $rec_2sync['NR_AVALIA'] . " = 0)
                                       AND '" . $rec_2sync['STATUS_AVALIA'] . "' = 'A'";

                    mysqli_query($bdint, $updt_bdint)
                        or die('erro na actualização(06) da BDInt: ' . mysqli_error($bdint) .
                                $nl . $nl . $updt_bdint);

                    if (mysqli_affected_rows($bdint) > 0) { $j++; }

                }

                if ($j > 0) {
                    $text = $j . " registos actualizados na BDInt";

                    if (!Is_cli()) {
                        echo '<script type="text/javascript">
                                  show_result("rep_AC_rec", "06", "' . $text . '", ' . ($tRecs + $delay) . ');
                              </script>';

                    } else {
                        echo $title . ": foram actualizados " . $j . " registos na BDInt.\n\n";

                    }

                } else {
                    if (!Is_cli()) {
                        echo '<script type="text/javascript">
                                  show_result("rep_AC_rec", "06", "sem registos p/ actualizar", ' . ($tRecs + $delay) . ');
                              </script>';

                    } else {
                        echo $title . ": sem registos p/ actualizar\n\n";

                    }

                }

            } else {
                $tRecs = 1000;

                if (!Is_cli()) {
                    echo '<div class="pbar" id="pbar06">
                              <script type="text/javascript">
                                  get_result("06", ' . $tRecs . ', ' . ($tRecs + $delay) . ');
                              </script>
                          </div>

                          <script type="text/javascript">
                              show_result("rep_AC_rec", "06", "sem registos p/ actualizar", ' . ($tRecs + $delay) . ');
                          </script>';

                } else {
                    echo $title . ": sem registos p/ actualizar\n\n";

                }

            }

    if (!Is_cli()) { echo '</div>'; }

$delay += ($tRecs + 3000);

// RECURSO (AF + AC N/A)
    $title = "RECURSO (AF + AC N/A)";

    if (!Is_cli()) {
        echo '<div id="NA_rec">
                  <script type="text/javascript">
                      add_title("NA_rec", "07", "' . $title . '", ' . $delay . ', "firebrick");
                  </script>';

    }

    // Obter notas negativas sincronizadas na BDInt
        $recs_2sync = array();

        $get_recs_2sync = "SELECT *
                           FROM lead.alunos_inscricoes
                           WHERE ((lead.alunos_inscricoes.MODO_AVA_N = 'F'
                                   AND lead.alunos_inscricoes.NR_AVALIA < 10)
                               OR (lead.alunos_inscricoes.MODO_AVA_N = 'C'
                                   AND lead.alunos_inscricoes.NR_AVALIA = 1))
                               AND lead.alunos_inscricoes.MODO_AVA_R IS NULL
                               AND lead.alunos_inscricoes.STATUS_AVALIA IN ('A', 'S')
                               AND lead.alunos_inscricoes.CD_LECTIVO >= '" . $lectyear . "'
                           ORDER BY RAND()
                           LIMIT 100";

        $result = mysqli_query($bdint, $get_recs_2sync)
                or die('erro na consulta(07) da BDInt: ' . mysqli_error($bdint) .
                        $nl . $nl . $get_recs_2sync);

        if (mysqli_num_rows($result) > 0) {
            while ($row = mysqli_fetch_assoc($result)) {
                $recs_2sync[] = $row;

            }

            $tRecs = count($recs_2sync);

            $j = 0;

            if (!Is_cli()) {
                echo '<div class="pbar" id="pbar07">
                          <script type="text/javascript">
                              get_result("07", ' . $tRecs . ', ' . ($tRecs + $delay) . ');
                          </script>
                      </div>';

            }

            // Percorrer o array de registos elegíveis p/ recurso
                foreach ($recs_2sync as $rec_2sync) {
                // actualiza BDInt
                    $updt_bdint = "UPDATE lead.alunos_inscricoes
                                   SET MODO_AVA_R = 'F'
                                   WHERE lead.alunos_inscricoes.CD_LECTIVO = " . $rec_2sync['CD_LECTIVO'] .
                                     " AND lead.alunos_inscricoes.CD_CURSO = " . $rec_2sync['CD_CURSO'] .
                                     " AND lead.alunos_inscricoes.CD_DISCIP = " . $rec_2sync['CD_DISCIP'] .
                                     " AND lead.alunos_inscricoes.CD_ALUNO = " . $rec_2sync['CD_ALUNO'] .
                                     " AND ('" . $rec_2sync['MODO_AVA_N'] . "' = 'F'
                                       AND " . $rec_2sync['NR_AVALIA'] . " < 10)
                                       AND '" . $rec_2sync['STATUS_AVALIA'] . "' = 'A'";

                    mysqli_query($bdint, $updt_bdint)
                        or die('erro na actualização(07) da BDInt: ' . mysqli_error($bdint) .
                                $nl . $nl . $updt_bdint);

                    if (mysqli_affected_rows($bdint) > 0) { $j++; }

                    $updt_bdint = "UPDATE lead.alunos_inscricoes
                                   SET MODO_AVA_R = 'F', NR_AVALIA = NULL
                                   WHERE lead.alunos_inscricoes.CD_LECTIVO = " . $rec_2sync['CD_LECTIVO'] .
                                     " AND lead.alunos_inscricoes.CD_CURSO = " . $rec_2sync['CD_CURSO'] .
                                     " AND lead.alunos_inscricoes.CD_DISCIP = " . $rec_2sync['CD_DISCIP'] .
                                     " AND lead.alunos_inscricoes.CD_ALUNO = " . $rec_2sync['CD_ALUNO'] .
                                     " AND ('" . $rec_2sync['MODO_AVA_N'] . "' = 'C'
                                       AND " . $rec_2sync['NR_AVALIA'] . " = 1)
                                       AND '" . $rec_2sync['STATUS_AVALIA'] . "' = 'A'";

                    mysqli_query($bdint, $updt_bdint)
                        or die('erro na actualização(08) da BDInt: ' . mysqli_error($bdint) .
                                $nl . $nl . $updt_bdint);

                    if (mysqli_affected_rows($bdint) > 0) { $j++; }

                }

                if ($j > 0) {
                    $text = $j . " registos actualizados na BDInt";

                    if (!Is_cli()) {
                        echo '<script type="text/javascript">
                                  show_result("NA_rec", "07", ' . $text . ($tRecs + $delay) . ');
                              </script>';

                    } else {
                        echo $title . ": foram actualizados " . $j . " registos na BDInt.\n\n";

                    }
                
                } else {
                    if (!Is_cli()) {
                        echo '<script type="text/javascript">
                                  show_result("NA_rec", "07", "sem registos p/ actualizar", ' . ($tRecs + $delay) . ');
                              </script>';

                    } else {
                        echo $title . ": sem registos p/ actualizar\n\n";

                    }

                }

            } else {
                $tRecs = 1000;

                if (!Is_cli()) {
                    echo '<div class="pbar" id="pbar07">
                              <script type="text/javascript">
                                  get_result("07", ' . $tRecs . ', ' . ($tRecs + $delay) . ');
                              </script>
                          </div>

                          <script type="text/javascript">
                              show_result("NA_rec", "07", "sem registos p/ actualizar", ' . ($tRecs + $delay) . ');
                          </script>';

                } else {
                    echo $title . ": sem registos p/ actualizar\n\n";

                }

            }

    if (!Is_cli()) { echo '</div>'; }

$delay += ($tRecs + 3000);

// MELHORIAS EM RECURSO
    $title = "MELHORIAS EM RECURSO";

    if (!Is_cli()) {
        echo '<div id="improv">
                  <script type="text/javascript">
                      add_title("improv", "08", "' . $title . '", ' . $delay . ', "teal");
                  </script>';

    }

    // Obter inscrições p/ melhoria em recurso no SiGES, a realizar em 7 dias
        $improv_reg = array();

        $get_improv_reg = "SELECT CSE.T_INSCRI.CD_LECTIVO,
                                  CSE.T_ALUNOS.CD_ALUNO,
                                  CSE.T_INSCRI.CD_DISCIP,
                                  (CASE WHEN CSE.T_AVALUNO.CD_GRU_AVA = 52 THEN 'E' ELSE 'X' END) AS PROVA,
                                  CSE.T_AVALUNO.DT_AVALIA
                           FROM (CSE.T_ALUNOS
                               INNER JOIN CSE.T_INSCRI ON (CSE.T_ALUNOS.CD_CURSO = CSE.T_INSCRI.CD_CURSO
                                   AND CSE.T_ALUNOS.CD_ALUNO = CSE.T_INSCRI.CD_ALUNO)
                               INNER JOIN CSE.T_AVALUNO ON (CSE.T_INSCRI.CD_DISCIP = CSE.T_AVALUNO.CD_DISCIP
                                   AND CSE.T_INSCRI.CD_DURACAO = CSE.T_AVALUNO.CD_DURACAO
                                   AND CSE.T_INSCRI.CD_ALUNO = CSE.T_AVALUNO.CD_ALUNO
                                   AND CSE.T_INSCRI.CD_CURSO = CSE.T_AVALUNO.CD_CURSO
                                   AND CSE.T_INSCRI.CD_LECTIVO = CSE.T_AVALUNO.CD_LECTIVO))
                               INNER JOIN CSE.T_TBEPOAVA ON CSE.T_AVALUNO.CD_GRU_AVA = CSE.T_TBEPOAVA.CD_GRU_AVA
                           WHERE CSE.T_INSCRI.CD_LECTIVO >= '" . $lectyear . "'
                               AND CSE.T_ALUNOS.CD_SITUA_FIN = 1
                               AND CSE.T_AVALUNO.CD_GRU_AVA IN (49, 52)
                               AND (to_char(CSE.T_AVALUNO.DT_AVALIA, 'YYYY-MM-DD') >= to_char(sysdate, 'YYYY-MM-DD')
                                   AND to_char(CSE.T_AVALUNO.DT_AVALIA, 'YYYY-MM-DD') <= to_char(sysdate+7, 'YYYY-MM-DD'))";

        $stmt = oci_parse($siges, $get_improv_reg);

        if (!$stmt) {
            $error = oci_error($siges);

            echo "erro de analise(11): " . $error['message'] .
                  $nl . $nl . $get_improv_reg;

            exit;

        }

        $result = oci_execute($stmt);

        if (!$result) {
            $error = oci_error($stmt);

            echo "erro na consulta(06) do SiGES: " . $error['message'] .
                  $nl . $nl . $get_improv_reg;

            exit;

        }

        $improv_reg = array();

        while (($row = oci_fetch_array($stmt, OCI_ASSOC+OCI_RETURN_NULLS)) != false) {
            $improv_reg[] = $row;

        }

        oci_free_statement($stmt);

    if (count($improv_reg) > 0) {
        $tRecs = count($improv_reg);

        $j = 0;

        if (!Is_cli()) {
            echo '<div class="pbar" id="pbar08">
                        <script type="text/javascript">
                            get_result("08", ' . $tRecs . ', ' . ($tRecs + $delay) . ');
                        </script>
                    </div>';

        }

        // Percorrer o array de inscrições p/ melhoria
        foreach ($improv_reg as $reg) {
            // Procurar dados do fluxo correspondente
            $get_flw_info = "SELECT SUBSTR(subtitle, 1, 5) AS course,
                                    evaltype,
                                    flowid
                             FROM wiseflow.flows
                             WHERE SUBSTR(subtitle, 1, 5) = '" . $reg['CD_DISCIP'] . "'
                                 AND lectyear = '" . $reg['CD_LECTIVO'] . "'
                                 AND evaltype = '" . $reg['PROVA'] . "'
                                 AND season = 'R'
                                 AND dtfrom >= now()";

            $flow = mysqli_query($bdint, $get_flw_info)
                    or die('erro na consulta(08) da BDInt: ' . mysqli_error($bdint) .
                            $nl . $nl . $get_flw_info);

            if (mysqli_num_rows($flow) <> 0) {
                while ($row = mysqli_fetch_assoc($flow)) {
                    $flowid = $row['flowid'];

                }

                // Procurar os dados das inscrições, nas provas a realizar
                $get_next_flows_info = "SELECT std.std_num AS CD_ALUNO,
                                               std.stdid AS WF_stdid,
                                               flw_ass.flowid AS WF_flowid
                                        FROM wiseflow.flows_assess AS flw_ass
                                            INNER JOIN wiseflow.students AS std ON std.stdid = flw_ass.stdid
                                        WHERE std.std_num = '" . $reg['CD_ALUNO'] . "'
                                            AND flw_ass.flowid = '" . $flowid . "'";

                $flows = mysqli_query($bdint, $get_next_flows_info)
                             or die('erro na consulta(09) da BDInt: ' . mysqli_error($bdint) .
                                     $nl . $nl . $get_next_flows_info);

                if (mysqli_num_rows($flows) == 0) {
                    // Procurar dados do estudante
                    $get_std_info = "SELECT std_num, stdid
                                     FROM wiseflow.students wf_std
                                         INNER JOIN lead.alunos_inscricoes ainsc ON ainsc.cd_aluno = wf_std.std_num
                                     WHERE std_num = '" . $reg['CD_ALUNO'] . "'
                                         AND ainsc.CD_DISCIP = '" . $reg['CD_DISCIP'] . "'
                                         AND ainsc.CD_LECTIVO = '" . $reg['CD_LECTIVO'] . "'";

                    $stdt = mysqli_query($bdint, $get_std_info)
                                or die('erro na consulta(10) da BDInt: ' . mysqli_error($bdint) .
                                        $nl . $nl . $get_std_info);
                    
                    while ($row = mysqli_fetch_assoc($stdt)) {
                        $stdid = $row['stdid'];
    
                    }
                
                    // actualiza BDInt
                    $updt_bdint = "INSERT IGNORE INTO wiseflow.flows_assess(stdid, flowid)
                                   VALUES ('" . (float)$stdid . "', '" . (float)$flowid . "')";

                    mysqli_query($bdint, $updt_bdint)
                        or die('erro na actualização(09) da BDInt: ' . mysqli_error($bdint) .
                                $nl . $nl . $updt_bdint);

                    if (mysqli_affected_rows($bdint) > 0) { $j++; }

                }
            
            }

        }

        if ($j > 0) {
            $text = $j . " registos actualizados na BDInt";

            if (!Is_cli()) {
                echo '<script type="text/javascript">
                          show_result("improv", "08", ' . $text . ($tRecs + $delay) . ');
                      </script>';

            } else {
                echo $title . ": foram actualizados " . $j . " registos na BDInt.\n\n";

            }

        } else {
            if (!Is_cli()) {
                echo '<script type="text/javascript">
                          show_result("improv", "08", "sem registos p/ actualizar", ' . ($tRecs + $delay) . ');
                      </script>';

            } else {
                echo $title . ": sem registos p/ actualizar\n\n";

            }

        }

    } else {
        $tRecs = 1000;

        if (!Is_cli()) {
            echo '<div class="pbar" id="pbar08">
                      <script type="text/javascript">
                          get_result("08", ' . $tRecs . ', ' . ($tRecs + $delay) . ');
                      </script>
                  </div>

                  <script type="text/javascript">
                      show_result("improv", "08", "sem registos p/ actualizar", ' . ($tRecs + $delay) . ');
                  </script>';

        } else {
            echo $title . ": sem registos p/ actualizar\n\n";

        }

    }

    if (!Is_cli()) { echo '</div>'; }

$delay += ($tRecs + 3000);

// ÉPOCA ESPECIAL
    $title = "EPOCA ESPECIAL";

    if (!Is_cli()) {
        echo '<div id="special">
                <script type="text/javascript">
                    add_title("special", "09", "' . $title . '", ' . $delay . ', "deepskyblue");
                </script>';

    }

    // Obter inscrições p/ época especial no SiGES, a realizar em 7 dias
        $special_reg = array();

        $get_special_reg = "SELECT CSE.T_INSCRI.CD_LECTIVO,
                                   CSE.T_ALUNOS.CD_ALUNO,
                                   CSE.T_INSCRI.CD_DISCIP,
                                   (CASE WHEN CSE.T_AVALUNO.CD_GRU_AVA = 3 THEN 'X' ELSE 'E' END) AS PROVA,
                                   CSE.T_AVALUNO.DT_AVALIA
                            FROM (CSE.T_ALUNOS
                                INNER JOIN CSE.T_INSCRI ON (CSE.T_ALUNOS.CD_CURSO = CSE.T_INSCRI.CD_CURSO
                                    AND CSE.T_ALUNOS.CD_ALUNO = CSE.T_INSCRI.CD_ALUNO)
                                INNER JOIN CSE.T_AVALUNO ON (CSE.T_INSCRI.CD_DISCIP = CSE.T_AVALUNO.CD_DISCIP
                                    AND CSE.T_INSCRI.CD_DURACAO = CSE.T_AVALUNO.CD_DURACAO
                                    AND CSE.T_INSCRI.CD_ALUNO = CSE.T_AVALUNO.CD_ALUNO
                                    AND CSE.T_INSCRI.CD_CURSO = CSE.T_AVALUNO.CD_CURSO
                                    AND CSE.T_INSCRI.CD_LECTIVO = CSE.T_AVALUNO.CD_LECTIVO))
                                INNER JOIN CSE.T_TBEPOAVA ON CSE.T_AVALUNO.CD_GRU_AVA = CSE.T_TBEPOAVA.CD_GRU_AVA
                            WHERE CSE.T_INSCRI.CD_LECTIVO >= '" . $lectyear . "'
                                AND CSE.T_ALUNOS.CD_SITUA_FIN = 1
                                AND CSE.T_AVALUNO.CD_GRU_AVA IN (3, 13)
                                AND (to_char(CSE.T_AVALUNO.DT_AVALIA, 'YYYY-MM-DD') >= to_char(sysdate, 'YYYY-MM-DD')
                                    AND to_char(CSE.T_AVALUNO.DT_AVALIA, 'YYYY-MM-DD') <= to_char(sysdate+7, 'YYYY-MM-DD'))";

        $stmt = oci_parse($siges, $get_special_reg);

        if (!$stmt) {
            $error = oci_error($siges);

            echo "erro de analise(12): " . $error['message'] .
                  $nl . $nl . $get_special_reg;

            exit;

        }

        $result = oci_execute($stmt);

        if (!$result) {
            $error = oci_error($stmt);

            echo "erro na consulta(07) do SiGES: " . $error['message'] .
                  $nl . $nl . $get_special_reg;

            exit;

        }

        $special_reg = array();
        
        while (($row = oci_fetch_array($stmt, OCI_ASSOC+OCI_RETURN_NULLS)) != false) {
            $special_reg[] = $row;

        }

        oci_free_statement($stmt);

    if (count($special_reg) > 0) {
        $tRecs = count($special_reg);

        $j = 0;

        if (!Is_cli()) {
            echo '<div class="pbar" id="pbar09">
                    <script type="text/javascript">
                        get_result("09", ' . $tRecs . ', ' . ($tRecs + $delay) . ');
                    </script>
                </div>';

        }

        // Percorrer o array de inscrições p/ época especial
        foreach ($special_reg as $reg) {
            // Procurar dados do fluxo correspondente
            $get_flw_info = "SELECT SUBSTR(subtitle, 1, 5) AS course,
                                    evaltype,
                                    flowid
                             FROM wiseflow.flows
                             WHERE SUBSTR(subtitle, 1, 5) = '" . $reg['CD_DISCIP'] . "'
                                 AND lectyear = '" . $reg['CD_LECTIVO'] . "'
                                 AND evaltype = '" . $reg['PROVA'] . "'
                                 AND season = 'E'
                                 AND dtfrom >= now()";

            $flow = mysqli_query($bdint, $get_flw_info)
                    or die('erro na consulta(11) da BDInt: ' . mysqli_error($bdint) .
                            $nl . $nl . $get_flw_info);

            if (mysqli_num_rows($flow) <> 0) {
                while ($row = mysqli_fetch_assoc($flow)) {
                    $flowid = $row['flowid'];

                }
    
                // Procurar os dados das inscrições, nas provas a realizar
                $get_next_flows_info = "SELECT std.std_num AS CD_ALUNO,
                                               std.stdid AS WF_stdid,
                                               flw_ass.flowid AS WF_flowid
                                        FROM wiseflow.flows_assess AS flw_ass
                                            INNER JOIN wiseflow.students AS std ON std.stdid = flw_ass.stdid
                                        WHERE std.std_num = '" . $reg['CD_ALUNO'] . "'
                                            AND flw_ass.flowid = '" . $flowid . "'";

                $flows = mysqli_query($bdint, $get_next_flows_info)
                             or die('erro na consulta(12) da BDInt: ' . mysqli_error($bdint) .
                                     $nl . $nl . $get_next_flows_info);

                if (mysqli_num_rows($flows) == 0) {
                    // Procurar dados do estudante
                    $get_std_info = "SELECT std_num, stdid
                                     FROM wiseflow.students wf_std
                                         INNER JOIN lead.alunos_inscricoes ainsc ON ainsc.cd_aluno = wf_std.std_num
                                     WHERE std_num = '" . $reg['CD_ALUNO'] . "'
                                         AND ainsc.CD_DISCIP = '" . $reg['CD_DISCIP'] . "'
                                         AND ainsc.CD_LECTIVO = '" . $reg['CD_LECTIVO'] . "'";

                    $stdt = mysqli_query($bdint, $get_std_info)
                                or die('erro na consulta(13) da BDInt: ' . mysqli_error($bdint) .
                                        $nl . $nl . $get_std_info);

                    while ($row = mysqli_fetch_assoc($stdt)) {
                        $stdid = $row['stdid'];

                    }

                    // actualiza BDInt
                    $updt_bdint = "INSERT IGNORE INTO wiseflow.flows_assess(stdid, flowid)
                                   VALUES ('" . (float)$stdid . "', '" . (float)$flowid . "')";

                    mysqli_query($bdint, $updt_bdint)
                        or die('erro na actualização(10) da BDInt: ' . mysqli_error($bdint) .
                                $nl . $nl . $updt_bdint);

                    if (mysqli_affected_rows($bdint) > 0) { $j++; }

                }

            }

        }

        if ($j > 0) {
            $text = $j . " registos actualizados na BDInt";

            if (!Is_cli()) {
                echo '<script type="text/javascript">
                          show_result("special", "09", ' . $text . ($tRecs + $delay) . ');
                      </script>';

            } else {
                echo $title . ": foram actualizados " . $j . " registos na BDInt.\n\n";

            }

        } else {
            if (!Is_cli()) {
                echo '<script type="text/javascript">
                          show_result("special", "09", "sem registos p/ actualizar", ' . ($tRecs + $delay) . ');
                      </script>';

            } else {
                echo $title . ": sem registos p/ actualizar\n\n";

            }

        }

    } else {
        $tRecs = 1000;

        if (!Is_cli()) {
            echo '<div class="pbar" id="pbar09">
                    <script type="text/javascript">
                        get_result("09", ' . $tRecs . ', ' . ($tRecs + $delay) . ');
                    </script>
                </div>

                <script type="text/javascript">
                    show_result("special", "09", "sem registos p/ actualizar", ' . ($tRecs + $delay) . ');
                </script>';

        } else {
            echo $title . ": sem registos p/ actualizar\n\n";

        }

    }

    if (!Is_cli()) { echo '</div>'; }

$delay += ($tRecs + 3000);

// actualiza data e hora da última execução
    $last_run = time();

    $set_last_run = "UPDATE lead.sync_siges_app
                     SET last_run = '" . $last_run . "'
                     WHERE lead.sync_siges_app.name = 'notas'";

    mysqli_query($bdint, $set_last_run)
        or die('erro na actualização(00) da BDInt: ' . mysqli_error($bdint) .
                $nl . $nl . $set_last_run);

    if ($alert == 1) {
        if ($color !== "green") { $color = "green"; }

        $email->Subject = "INFO: servico reposto";
        $email->Body = '<br>ultima execucao: <span style="color: ' . $color . '; font-weight: bold;">' . date("Y-m-d H:i:s", $last_run) . '</span>
                        <br><br>(sent automatically from root@00app10)';

        if ($email->Send()) {
            echo $nl . 'INFO: e-mail enviado' . $nl;

            $set_alert = "UPDATE lead.sync_siges_app
                          SET alert = 0
                          WHERE name = 'notas'";

            mysqli_query($bdint, $set_alert)
                or die('erro na actualização(14) da BDInt: ' . mysqli_error($bdint) .
                        $nl . $nl . $set_alert);

        } else {
            echo $nl . 'ALERTA: e-mail NAO enviado | erro: ' . $email->ErrorInfo . $nl;

        }

    }

// Encerrar conexão ao SiGES
oci_close($siges);

// Encerrar conexão à BDInt
mysqli_close($bdint);

if (!Is_cli()) {
    echo '</div>

          <script type="text/javascript">
              the_end(' . $delay . ');
          </script>
      
      </body></html>';

}
