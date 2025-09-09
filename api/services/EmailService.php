<?php
namespace App\Services;

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

class EmailService {
    private $mailer;
    
    public function __construct() {
        $this->mailer = new PHPMailer(true);
        $this->configureMailer();
    }
    
    private function configureMailer() {
        // Configuración del servidor
        $this->mailer->isSMTP();
        $this->mailer->Host       = $_ENV['MAIL_HOST'];
        $this->mailer->SMTPAuth   = true;
        $this->mailer->Username   = $_ENV['MAIL_USERNAME'];
        $this->mailer->Password   = $_ENV['MAIL_PASSWORD'];
        $this->mailer->SMTPSecure = $_ENV['MAIL_ENCRYPTION'];
        $this->mailer->Port       = $_ENV['MAIL_PORT'];
        
        // Configuración automática según entorno
        if (isset($_ENV['APP_ENV']) && $_ENV['APP_ENV'] === 'development') {
            // Solo en desarrollo: deshabilitar verificación SSL
            $this->mailer->SMTPOptions = array(
                'ssl' => array(
                    'verify_peer' => false,
                    'verify_peer_name' => false,
                    'allow_self_signed' => true
                )
            );
        }
        // En producción no se añade SMTPOptions, usa certificados normalmente
        
        // Configuración del remitente
        $this->mailer->setFrom($_ENV['MAIL_FROM_ADDRESS'], $_ENV['MAIL_FROM_NAME']);
        
        // Configuración adicional
        $this->mailer->CharSet = 'UTF-8';
        $this->mailer->isHTML(true);
    }
    
    /**
     * Enviar código 2FA por email
     */
    public function sendTwoFactorCode($email, $name, $code) {
        try {
            // Limpiar destinatarios anteriores
            $this->mailer->clearAddresses();
            
            // Añadir destinatario
            $this->mailer->addAddress($email, $name);
            
            // Asunto
            $this->mailer->Subject = 'Código de verificación - TaxiClass';
            
            // Cuerpo del email en HTML
            $this->mailer->Body = $this->getTwoFactorEmailTemplate($name, $code);
            
            // Versión texto plano
            $this->mailer->AltBody = "Hola $name,\n\nTu código de verificación es: $code\n\nEste código expirará en 10 minutos.\n\nSaludos,\nEquipo TaxiClass";
            
            // Enviar email
            $this->mailer->send();
            
            return [
                'success' => true,
                'message' => 'Código enviado correctamente'
            ];
            
        } catch (Exception $e) {
            error_log("Error al enviar email: " . $this->mailer->ErrorInfo);
            return [
                'success' => false,
                'message' => 'Error al enviar el código',
                'error' => $this->mailer->ErrorInfo
            ];
        }
    }
    
    /**
     * Plantilla HTML para el email de 2FA
     */
    private function getTwoFactorEmailTemplate($name, $code) {
        return '
        <!DOCTYPE html>
        <html lang="es">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>Código de verificación</title>
        </head>
        <body style="margin: 0; padding: 0; font-family: Arial, sans-serif; background-color: #f4f4f4;">
            <table cellpadding="0" cellspacing="0" width="100%" style="background-color: #f4f4f4; padding: 20px 0;">
                <tr>
                    <td align="center">
                        <table cellpadding="0" cellspacing="0" width="600" style="background-color: #ffffff; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
                            <!-- Header -->
                            <tr>
                                <td align="center" style="background-color: #011850; padding: 40px 0; border-radius: 8px 8px 0 0;">
                                    <h1 style="color: #ffffff; margin: 0; font-size: 32px;">TaxiClass</h1>
                                    <p style="color: #05D9D9; margin: 10px 0 0 0; font-size: 16px;">Servicio de Reservas</p>
                                </td>
                            </tr>
                            
                            <!-- Content -->
                            <tr>
                                <td style="padding: 40px 30px;">
                                    <h2 style="color: #333333; margin: 0 0 20px 0;">Hola ' . htmlspecialchars($name) . ',</h2>
                                    <p style="color: #666666; font-size: 16px; line-height: 1.5; margin: 0 0 30px 0;">
                                        Has solicitado iniciar sesión en tu cuenta. Utiliza el siguiente código de verificación:
                                    </p>
                                    
                                    <!-- Code Box -->
                                    <table cellpadding="0" cellspacing="0" width="100%">
                                        <tr>
                                            <td align="center" style="padding: 30px 0;">
                                                <div style="background-color: #f8f9fa; border: 2px solid #05D9D9; border-radius: 8px; padding: 20px; display: inline-block;">
                                                    <h1 style="color: #011850; margin: 0; font-size: 36px; letter-spacing: 8px;">' . $code . '</h1>
                                                </div>
                                            </td>
                                        </tr>
                                    </table>
                                    
                                    <p style="color: #666666; font-size: 14px; line-height: 1.5; margin: 30px 0 0 0;">
                                        <strong>⏱️ Este código expirará en 10 minutos.</strong>
                                    </p>
                                    
                                    <p style="color: #999999; font-size: 14px; line-height: 1.5; margin: 20px 0 0 0;">
                                        Si no has solicitado este código, puedes ignorar este mensaje. Tu cuenta permanecerá segura.
                                    </p>
                                </td>
                            </tr>
                            
                            <!-- Footer -->
                            <tr>
                                <td style="background-color: #f8f9fa; padding: 30px; border-radius: 0 0 8px 8px; text-align: center;">
                                    <p style="color: #999999; font-size: 12px; margin: 0;">
                                        © ' . date('Y') . ' TaxiClass. Todos los derechos reservados.
                                    </p>
                                    <p style="color: #999999; font-size: 12px; margin: 10px 0 0 0;">
                                        Este es un mensaje automático, por favor no responda a este email.
                                    </p>
                                </td>
                            </tr>
                        </table>
                    </td>
                </tr>
            </table>
        </body>
        </html>';
    }
    
    /**
     * Enviar email de bienvenida cuando se activa 2FA
     */
    public function sendTwoFactorActivationEmail($email, $name) {
        try {
            $this->mailer->clearAddresses();
            $this->mailer->addAddress($email, $name);
            $this->mailer->Subject = 'Verificación de dos pasos activada - TaxiClass';
            
            $this->mailer->Body = '
            <!DOCTYPE html>
            <html lang="es">
            <head>
                <meta charset="UTF-8">
                <meta name="viewport" content="width=device-width, initial-scale=1.0">
            </head>
            <body style="margin: 0; padding: 0; font-family: Arial, sans-serif; background-color: #f4f4f4;">
                <table cellpadding="0" cellspacing="0" width="100%" style="background-color: #f4f4f4; padding: 20px 0;">
                    <tr>
                        <td align="center">
                            <table cellpadding="0" cellspacing="0" width="600" style="background-color: #ffffff; border-radius: 8px;">
                                <tr>
                                    <td align="center" style="background-color: #011850; padding: 40px 0; border-radius: 8px 8px 0 0;">
                                        <h1 style="color: #ffffff; margin: 0;">TaxiClass</h1>
                                    </td>
                                </tr>
                                <tr>
                                    <td style="padding: 40px 30px;">
                                        <h2 style="color: #333333;">Hola ' . htmlspecialchars($name) . ',</h2>
                                        <p style="color: #666666; font-size: 16px; line-height: 1.5;">
                                            ✅ La verificación de dos pasos ha sido activada exitosamente en tu cuenta.
                                        </p>
                                        <p style="color: #666666; font-size: 16px; line-height: 1.5;">
                                            A partir de ahora, cada vez que inicies sesión, te enviaremos un código de verificación a este email.
                                        </p>
                                        <p style="color: #999999; font-size: 14px; margin-top: 30px;">
                                            Si no has realizado este cambio, contacta con nosotros inmediatamente.
                                        </p>
                                    </td>
                                </tr>
                                <tr>
                                    <td style="background-color: #f8f9fa; padding: 30px; text-align: center;">
                                        <p style="color: #999999; font-size: 12px; margin: 0;">
                                            © ' . date('Y') . ' TaxiClass. Todos los derechos reservados.
                                        </p>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>
                </table>
            </body>
            </html>';
            
            $this->mailer->AltBody = "Hola $name,\n\nLa verificación de dos pasos ha sido activada en tu cuenta.\n\nSaludos,\nEquipo TaxiClass";
            
            $this->mailer->send();
            return ['success' => true];
            
        } catch (Exception $e) {
            error_log("Error al enviar email de activación 2FA: " . $this->mailer->ErrorInfo);
            return ['success' => false, 'error' => $this->mailer->ErrorInfo];
        }
    }
    
    /**
     * Enviar email de confirmación de reserva al usuario
     */
    public function sendReservationConfirmation($email, $name, $reservationData) {
        try {
            $this->mailer->clearAddresses();
            $this->mailer->addAddress($email, $name);
            $this->mailer->Subject = 'Confirmación de Reserva #' . $reservationData['bookingId'] . ' - TaxiClass';
            
            $this->mailer->Body = $this->getReservationConfirmationTemplate($name, $reservationData);
            
            // Versión texto plano
            $plainText = "Hola $name,\n\n";
            $plainText .= "Tu reserva ha sido confirmada exitosamente.\n\n";
            $plainText .= "Número de reserva: #" . $reservationData['bookingId'] . "\n";
            $plainText .= "Fecha: " . $reservationData['date'] . "\n";
            $plainText .= "Hora: " . $reservationData['time'] . "\n";
            $plainText .= "Recogida: " . strip_tags($reservationData['pickupAddress']) . "\n";
            $plainText .= "Destino: " . strip_tags($reservationData['destinationAddress']) . "\n\n";
            $plainText .= "Saludos,\nEquipo TaxiClass";
            
            $this->mailer->AltBody = $plainText;
            
            $this->mailer->send();
            
            return [
                'success' => true,
                'message' => 'Email de confirmación enviado'
            ];
            
        } catch (Exception $e) {
            error_log("Error al enviar email de confirmación: " . $this->mailer->ErrorInfo);
            return [
                'success' => false,
                'message' => 'Error al enviar email',
                'error' => $this->mailer->ErrorInfo
            ];
        }
    }
    
    /**
     * Enviar notificación de nueva reserva a administración
     */
    public function sendReservationNotificationToAdmin($adminEmail, $reservationData) {
        try {
            $this->mailer->clearAddresses();
            $this->mailer->addAddress($adminEmail, 'Administración TaxiClass');
            $this->mailer->Subject = 'Nueva Reserva #' . $reservationData['bookingId'] . ' - ' . $reservationData['userName'];
            
            $this->mailer->Body = $this->getAdminNotificationTemplate($reservationData);
            
            // Versión texto plano
            $plainText = "NUEVA RESERVA RECIBIDA\n\n";
            $plainText .= "Número de reserva: #" . $reservationData['bookingId'] . "\n";
            $plainText .= "Cliente: " . $reservationData['userName'] . "\n";
            $plainText .= "Email: " . $reservationData['userEmail'] . "\n";
            $plainText .= "Teléfono: " . $reservationData['userPhone'] . "\n";
            $plainText .= "Cuenta: " . $reservationData['account'] . "\n\n";
            $plainText .= "DETALLES DEL SERVICIO:\n";
            $plainText .= "Fecha: " . $reservationData['date'] . "\n";
            $plainText .= "Hora: " . $reservationData['time'] . "\n";
            $plainText .= "Recogida: " . strip_tags($reservationData['pickupAddress']) . "\n";
            $plainText .= "Destino: " . strip_tags($reservationData['destinationAddress']) . "\n";
            $plainText .= "Pasajeros: " . $reservationData['passengers'] . "\n";
            $plainText .= "Tipo de vehículo: " . $reservationData['vehicleType'] . "\n";
            
            $this->mailer->AltBody = $plainText;
            
            $this->mailer->send();
            
            return [
                'success' => true,
                'message' => 'Notificación enviada a administración'
            ];
            
        } catch (Exception $e) {
            error_log("Error al enviar notificación a admin: " . $this->mailer->ErrorInfo);
            return [
                'success' => false,
                'message' => 'Error al enviar notificación',
                'error' => $this->mailer->ErrorInfo
            ];
        }
    }
    
    /**
     * Plantilla HTML para confirmación de reserva (usuario)
     */
    private function getReservationConfirmationTemplate($name, $data) {
        $html = '<!DOCTYPE html>
        <html lang="es">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>Confirmación de Reserva</title>
        </head>
        <body style="margin: 0; padding: 0; font-family: Arial, sans-serif; background-color: #f4f4f4;">
            <table cellpadding="0" cellspacing="0" width="100%" style="background-color: #f4f4f4; padding: 20px 0;">
                <tr>
                    <td align="center">
                        <table cellpadding="0" cellspacing="0" width="600" style="background-color: #ffffff; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
                            <!-- Header -->
                            <tr>
                                <td align="center" style="background-color: #011850; padding: 40px 0; border-radius: 8px 8px 0 0;">
                                    <h1 style="color: #ffffff; margin: 0; font-size: 32px;">TaxiClass</h1>
                                    <p style="color: #05D9D9; margin: 10px 0 0 0; font-size: 16px;">Confirmación de Reserva</p>
                                </td>
                            </tr>
                            
                            <!-- Content -->
                            <tr>
                                <td style="padding: 40px 30px;">
                                    <h2 style="color: #333333; margin: 0 0 20px 0;">Hola ' . htmlspecialchars($name) . ',</h2>
                                    <p style="color: #666666; font-size: 16px; line-height: 1.5; margin: 0 0 30px 0;">
                                        Tu reserva ha sido confirmada exitosamente. A continuación encontrarás todos los detalles:
                                    </p>
                                    
                                    <!-- Booking Reference -->
                                    <div style="background-color: #f8f9fa; border-left: 4px solid #05D9D9; padding: 20px; margin-bottom: 30px;">
                                        <p style="margin: 0; color: #666666; font-size: 14px;">Número de reserva:</p>
                                        <h2 style="margin: 5px 0; color: #011850; font-size: 28px;">#' . $data['bookingId'] . '</h2>';
        
        if (!empty($data['serviceId'])) {
            $html .= '<p style="margin: 5px 0 0 0; color: #999999; font-size: 12px;">ID de servicio: ' . $data['serviceId'] . '</p>';
        }
        
        $html .= '</div>
                                    
                                    <!-- Service Details -->
                                    <h3 style="color: #011850; margin: 30px 0 15px 0; border-bottom: 2px solid #f0f0f0; padding-bottom: 10px;">
                                        📅 Fecha y Hora
                                    </h3>
                                    <p style="margin: 0 0 20px 0; color: #666666; font-size: 16px;">
                                        <strong>' . $data['date'] . '</strong> a las <strong>' . $data['time'] . '</strong> horas
                                    </p>
                                    
                                    <h3 style="color: #011850; margin: 30px 0 15px 0; border-bottom: 2px solid #f0f0f0; padding-bottom: 10px;">
                                        📍 Trayecto
                                    </h3>
                                    <div style="margin: 0 0 20px 0;">
                                        <p style="margin: 0 0 10px 0; color: #666666;">
                                            <strong>Recogida:</strong><br>
                                            ' . nl2br(htmlspecialchars($data['pickupAddress'])) . '
                                        </p>
                                        <p style="margin: 10px 0 0 0; color: #666666;">
                                            <strong>Destino:</strong><br>
                                            ' . nl2br(htmlspecialchars($data['destinationAddress'])) . '
                                        </p>
                                    </div>
                                    
                                    <h3 style="color: #011850; margin: 30px 0 15px 0; border-bottom: 2px solid #f0f0f0; padding-bottom: 10px;">
                                        🚗 Detalles del Servicio
                                    </h3>
                                    <table width="100%" style="margin: 0 0 20px 0;">
                                        <tr>
                                            <td style="padding: 5px 0; color: #666666;">Pasajeros:</td>
                                            <td style="padding: 5px 0; color: #666666; text-align: right;"><strong>' . $data['passengers'] . '</strong></td>
                                        </tr>
                                        <tr>
                                            <td style="padding: 5px 0; color: #666666;">Tipo de vehículo:</td>
                                            <td style="padding: 5px 0; color: #666666; text-align: right;"><strong>' . $data['vehicleType'] . '</strong></td>
                                        </tr>';
        
        if (!empty($data['extras']) && $data['extras'] !== 'Ninguno') {
            $html .= '<tr>
                        <td style="padding: 5px 0; color: #666666;">Extras:</td>
                        <td style="padding: 5px 0; color: #666666; text-align: right;"><strong>' . $data['extras'] . '</strong></td>
                    </tr>';
        }
        
        if (!empty($data['providerName'])) {
            $html .= '<tr>
                        <td style="padding: 5px 0; color: #666666;">Proveedor:</td>
                        <td style="padding: 5px 0; color: #666666; text-align: right;"><strong>' . $data['providerName'] . '</strong></td>
                    </tr>';
        }
        
        $html .= '</table>';
        
        if (!empty($data['specialInstructions'])) {
            $html .= '<h3 style="color: #011850; margin: 30px 0 15px 0; border-bottom: 2px solid #f0f0f0; padding-bottom: 10px;">
                        📝 Observaciones
                    </h3>
                    <p style="margin: 0 0 20px 0; color: #666666;">
                        ' . nl2br(htmlspecialchars($data['specialInstructions'])) . '
                    </p>';
        }
        
        $html .= '
                                    <!-- Important Notice -->
                                    <div style="background-color: #fef3c7; border: 1px solid #fbbf24; border-radius: 8px; padding: 20px; margin: 30px 0;">
                                        <h4 style="color: #92400e; margin: 0 0 10px 0;">ℹ️ Información Importante</h4>
                                        <p style="color: #92400e; font-size: 14px; line-height: 1.5; margin: 0;">
                                            El conductor se pondrá en contacto contigo antes del servicio para confirmar los detalles. 
                                            Por favor, asegúrate de tener tu teléfono disponible.
                                        </p>
                                    </div>
                                    
                                    <!-- Contact Info -->
                                    <p style="color: #666666; font-size: 14px; line-height: 1.5; margin: 30px 0 0 0; text-align: center;">
                                        Si necesitas modificar o cancelar tu reserva, accede a tu cuenta en nuestra plataforma 
                                        o contacta con nosotros.
                                    </p>
                                </td>
                            </tr>
                            
                            <!-- Footer -->
                            <tr>
                                <td style="background-color: #f8f9fa; padding: 30px; border-radius: 0 0 8px 8px; text-align: center;">
                                    <p style="color: #999999; font-size: 12px; margin: 0;">
                                        © ' . date('Y') . ' TaxiClass. Todos los derechos reservados.
                                    </p>
                                    <p style="color: #999999; font-size: 12px; margin: 10px 0 0 0;">
                                        Este email ha sido generado automáticamente. Por favor, no responda a este mensaje.
                                    </p>
                                </td>
                            </tr>
                        </table>
                    </td>
                </tr>
            </table>
        </body>
        </html>';
        
        return $html;
    }
    
    /**
     * Plantilla HTML para notificación a administración
     */
    private function getAdminNotificationTemplate($data) {
        $html = '<!DOCTYPE html>
        <html lang="es">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>Nueva Reserva</title>
        </head>
        <body style="margin: 0; padding: 0; font-family: Arial, sans-serif; background-color: #f4f4f4;">
            <table cellpadding="0" cellspacing="0" width="100%" style="background-color: #f4f4f4; padding: 20px 0;">
                <tr>
                    <td align="center">
                        <table cellpadding="0" cellspacing="0" width="600" style="background-color: #ffffff; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
                            <!-- Header -->
                            <tr>
                                <td align="center" style="background-color: #dc2626; padding: 30px 0; border-radius: 8px 8px 0 0;">
                                    <h1 style="color: #ffffff; margin: 0; font-size: 28px;">NUEVA RESERVA</h1>
                                    <p style="color: #ffffff; margin: 10px 0 0 0; font-size: 14px;">Sistema de Administración TaxiClass</p>
                                </td>
                            </tr>
                            
                            <!-- Content -->
                            <tr>
                                <td style="padding: 30px;">
                                    <!-- Booking Info -->
                                    <div style="background-color: #fee2e2; border-left: 4px solid #dc2626; padding: 15px; margin-bottom: 25px;">
                                        <p style="margin: 0; color: #7f1d1d; font-size: 14px;">Número de reserva:</p>
                                        <h2 style="margin: 5px 0; color: #dc2626; font-size: 24px;">#' . $data['bookingId'] . '</h2>';
        
        if (!empty($data['serviceId'])) {
            $html .= '<p style="margin: 5px 0 0 0; color: #7f1d1d; font-size: 12px;">ID de servicio: ' . $data['serviceId'] . '</p>';
        }
        
        $html .= '</div>
                                    
                                    <!-- Customer Info -->
                                    <h3 style="color: #374151; margin: 0 0 15px 0; font-size: 18px; border-bottom: 2px solid #e5e7eb; padding-bottom: 8px;">
                                        👤 Información del Cliente
                                    </h3>
                                    <table width="100%" style="margin: 0 0 25px 0;">
                                        <tr>
                                            <td style="padding: 5px 0; color: #6b7280;">Nombre:</td>
                                            <td style="padding: 5px 0; color: #374151;"><strong>' . htmlspecialchars($data['userName']) . '</strong></td>
                                        </tr>
                                        <tr>
                                            <td style="padding: 5px 0; color: #6b7280;">Email:</td>
                                            <td style="padding: 5px 0; color: #374151;">
                                                <a href="mailto:' . $data['userEmail'] . '" style="color: #2563eb; text-decoration: none;">
                                                    ' . htmlspecialchars($data['userEmail']) . '
                                                </a>
                                            </td>
                                        </tr>
                                        <tr>
                                            <td style="padding: 5px 0; color: #6b7280;">Teléfono:</td>
                                            <td style="padding: 5px 0; color: #374151;">
                                                <a href="tel:' . $data['userPhone'] . '" style="color: #2563eb; text-decoration: none;">
                                                    ' . htmlspecialchars($data['userPhone']) . '
                                                </a>
                                            </td>
                                        </tr>
                                        <tr>
                                            <td style="padding: 5px 0; color: #6b7280;">Cuenta:</td>
                                            <td style="padding: 5px 0; color: #374151;"><strong>' . htmlspecialchars($data['account']) . '</strong></td>
                                        </tr>
                                    </table>
                                    
                                    <!-- Service Details -->
                                    <h3 style="color: #374151; margin: 0 0 15px 0; font-size: 18px; border-bottom: 2px solid #e5e7eb; padding-bottom: 8px;">
                                        🚗 Detalles del Servicio
                                    </h3>
                                    <table width="100%" style="margin: 0 0 25px 0;">
                                        <tr style="background-color: #f9fafb;">
                                            <td style="padding: 8px; color: #6b7280;">Fecha:</td>
                                            <td style="padding: 8px; color: #374151;"><strong>' . $data['date'] . '</strong></td>
                                        </tr>
                                        <tr>
                                            <td style="padding: 8px; color: #6b7280;">Hora:</td>
                                            <td style="padding: 8px; color: #374151;"><strong>' . $data['time'] . '</strong></td>
                                        </tr>
                                        <tr style="background-color: #f9fafb;">
                                            <td style="padding: 8px; color: #6b7280;">Pasajeros:</td>
                                            <td style="padding: 8px; color: #374151;"><strong>' . $data['passengers'] . '</strong></td>
                                        </tr>
                                        <tr>
                                            <td style="padding: 8px; color: #6b7280;">Tipo de vehículo:</td>
                                            <td style="padding: 8px; color: #374151;"><strong>' . $data['vehicleType'] . '</strong></td>
                                        </tr>';
        
        if (!empty($data['extras']) && $data['extras'] !== 'Ninguno') {
            $html .= '<tr style="background-color: #f9fafb;">
                        <td style="padding: 8px; color: #6b7280;">Extras:</td>
                        <td style="padding: 8px; color: #374151;"><strong>' . $data['extras'] . '</strong></td>
                    </tr>';
        }
        
        if (!empty($data['providerName'])) {
            $html .= '<tr>
                        <td style="padding: 8px; color: #6b7280;">Proveedor asignado:</td>
                        <td style="padding: 8px; color: #374151;"><strong>' . $data['providerName'] . '</strong></td>
                    </tr>';
        }
        
        $html .= '</table>
                                    
                                    <!-- Locations -->
                                    <h3 style="color: #374151; margin: 0 0 15px 0; font-size: 18px; border-bottom: 2px solid #e5e7eb; padding-bottom: 8px;">
                                        📍 Trayecto
                                    </h3>
                                    <div style="margin: 0 0 25px 0;">
                                        <div style="background-color: #ecfdf5; border-left: 4px solid #10b981; padding: 15px; margin-bottom: 15px;">
                                            <p style="margin: 0 0 5px 0; color: #065f46; font-weight: bold;">RECOGIDA:</p>
                                            <p style="margin: 0; color: #047857;">
                                                ' . nl2br(htmlspecialchars($data['pickupAddress'])) . '
                                            </p>
                                        </div>
                                        <div style="background-color: #fef3c7; border-left: 4px solid #f59e0b; padding: 15px;">
                                            <p style="margin: 0 0 5px 0; color: #78350f; font-weight: bold;">DESTINO:</p>
                                            <p style="margin: 0; color: #92400e;">
                                                ' . nl2br(htmlspecialchars($data['destinationAddress'])) . '
                                            </p>
                                        </div>
                                    </div>';
        
        if (!empty($data['specialInstructions'])) {
            $html .= '
                                    <!-- Special Instructions -->
                                    <h3 style="color: #374151; margin: 0 0 15px 0; font-size: 18px; border-bottom: 2px solid #e5e7eb; padding-bottom: 8px;">
                                        📝 Observaciones del Cliente
                                    </h3>
                                    <div style="background-color: #f3f4f6; border-radius: 8px; padding: 15px; margin: 0 0 25px 0;">
                                        <p style="margin: 0; color: #4b5563; font-style: italic;">
                                            "' . nl2br(htmlspecialchars($data['specialInstructions'])) . '"
                                        </p>
                                    </div>';
        }
        
        $html .= '
                                    <!-- Timestamp -->
                                    <p style="color: #9ca3af; font-size: 12px; text-align: center; margin: 30px 0 0 0; padding-top: 20px; border-top: 1px solid #e5e7eb;">
                                        Reserva recibida el ' . date('d/m/Y') . ' a las ' . date('H:i') . ' horas
                                    </p>
                                </td>
                            </tr>
                            
                            <!-- Footer -->
                            <tr>
                                <td style="background-color: #1f2937; padding: 20px; border-radius: 0 0 8px 8px; text-align: center;">
                                    <p style="color: #d1d5db; font-size: 12px; margin: 0;">
                                        Sistema de Gestión de Reservas - TaxiClass
                                    </p>
                                    <p style="color: #9ca3af; font-size: 11px; margin: 8px 0 0 0;">
                                        Este es un email automático del sistema. No responder.
                                    </p>
                                </td>
                            </tr>
                        </table>
                    </td>
                </tr>
            </table>
        </body>
        </html>';
        
        return $html;
    }
}
