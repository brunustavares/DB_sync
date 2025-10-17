<?php
/**
 * auth parameters for Moodle and BDInt (MySQL) and SiGES (Oracle) connections
 * (developed for UAb - Universidade Aberta)
 *
 * @package    auth_lib_dbs
 * @category   php_config
 * @author     Bruno Tavares <brunustavares@gmail.com>
 * @link       https://www.linkedin.com/in/brunomastavares/
 * @copyright  Copyright (C) 2024-2025 Bruno Tavares
 * @license    GNU General Public License v3 or later
 *             https://www.gnu.org/licenses/gpl-3.0.html
 * @version    2025020709
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

// e-mail settings
    /**
     * configuração do serviço de e-mail
     *
     */
    require '(...)PHPMailer//src//PHPMailer.php';
    require '(...)PHPMailer//src//SMTP.php';

    use PHPMailer\PHPMailer\PHPMailer;
    use PHPMailer\PHPMailer\SMTP;

    $email = new PHPMailer(true);

    $email->isSMTP();
    $email->Host = "<your_hidden_host>";
    $email->Port = "<your_hidden_port>";
    $email->SMTPAuth = true;
    $email->SMTPSecure = 'tls';
    $email->SMTPOptions = array(
                                'ssl' => array(
                                               'verify_peer' => false,
                                               'verify_peer_name' => false,
                                               'allow_self_signed' => true
                                              )
    );
    $email->Username = '<your_hidden_username>';
    $email->Password = '<your_hidden_password>';
    $email->SMTPDebug = 2;

    $email->setFrom('<your_hidden_from>', '_DBsync_');
    $email->AddAddress('<your_hidden_address>', 'receiver');
    $email->AddCC('<your_hidden_cc>', 'CC');

    $email->IsHTML(true);
    $email->CharSet = 'UTF-8';

// PlataformAbERTA
    /**
     * Conexão ao web service da PlataformAbERTA
     *
     * @return string
     */
    function connect2mdl($endpoint, $al=null, $stds=null, $ucs=null)
    {
        $mdl_wsURL = '<your_hidden_url>';
        $mdl_token = '<your_hidden_token>';

        $connection = $mdl_wsURL
                    . '?wstoken=' . $mdl_token
                    . '&wsfunction=' . $endpoint
                    . (($al) ? '&ano_lectivo=' . $al : null)
                    . (($stds) ? '&lista_stds=' . base64_encode($stds) : "&lista_stds=")
                    . (($ucs) ? '&lista_ucs=' . base64_encode($ucs) : "&lista_ucs=")
                    . '&moodlewsrestformat=json';

        return $connection;

    }

// base de dados intermédia - BDInt
    /**
     * Conexão à BDInt
     *
     * @return mysqli connection
     */
    function connect2bdint()
    {
        $BDInt_host = '<your_hidden_host>';
        $BDInt_port = '<your_hidden_port>';
        $BDInt_db   = '<your_hidden_db>';
        $BDInt_usr  = '<your_hidden_user>';
        $BDInt_pwd  = '<your_hidden_password>';

        $connection = mysqli_connect($BDInt_host,
                                     $BDInt_usr,
                                     $BDInt_pwd,
                                     $BDInt_db,
                                     $BDInt_port)
                          or die('Ñ foi possível aceder à BDInt: ' . mysqli_connect_error());

        return $connection;

    }

// SiGES
    /**
     * Conexão ao SiGES
     *
     * @return oci8 connection
     */
    function connect2siges()
    {
        $Oracle_host = "<your_hidden_host>";
        $Oracle_port = "<your_hidden_port>";
        $Oracle_db   = "<your_hidden_db>";
        $Oracle_usr  = "<your_hidden_user>";
        $Oracle_pwd  = "<your_hidden_password>";

        $connection = oci_connect($Oracle_usr,
                                  $Oracle_pwd,
                                  "(DESCRIPTION=
                                                (ADDRESS=
                                                         (PROTOCOL=TCP)
                                                         (HOST=" . $Oracle_host . ")
                                                         (PORT=" . $Oracle_port . ")
                                                )
                                                (CONNECT_DATA=
                                                    	      (SERVICE_NAME=" . $Oracle_db . ")
                                                )
                                   )"
                                 );
        
        if (!$connection) {
            $error = oci_error();

            die('Ñ foi possível aceder ao SiGES: ' . $error['message']);

        } else {
            return $connection;
        
        }
            
    }
