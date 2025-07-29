<?php

namespace Services;

use Helpers\Config;
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

class EmailService
{
    private $config;
    
    public function __construct()
    {
        $this->config = require dirname(__DIR__, 2) . '/config/config.development.php';
    }
    
    /**
     * Send payment confirmation email
     */
    public function sendPaymentConfirmation($userEmail, $userName, $orderData)
    {
        $subject = "Payment Confirmation - Order #{$orderData['order_id']}";
        
        $htmlBody = $this->getPaymentConfirmationTemplate($userName, $orderData);
        $textBody = $this->getPaymentConfirmationTextTemplate($userName, $orderData);
        
        return $this->sendEmail($userEmail, $subject, $htmlBody, $textBody);
    }
    
    /**
     * Get HTML template for payment confirmation
     */
    private function getPaymentConfirmationTemplate($userName, $orderData)
    {
        $baseUrl = $this->config['base_url'] ?? 'http://localhost:3000';
        $myNotesUrl = $baseUrl . '/my-notes';
        
        return "
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset='utf-8'>
            <meta name='viewport' content='width=device-width, initial-scale=1.0'>
            <title>Payment Confirmation</title>
            <style>
                body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                .header { background: #3B82F6; color: white; padding: 30px; text-align: center; border-radius: 8px 8px 0 0; }
                .content { background: #f9fafb; padding: 30px; border-radius: 0 0 8px 8px; }
                .success-icon { font-size: 48px; margin-bottom: 10px; }
                .order-details { background: white; padding: 20px; border-radius: 8px; margin: 20px 0; }
                .order-row { display: flex; justify-content: space-between; margin: 10px 0; }
                .total { border-top: 2px solid #e5e7eb; padding-top: 10px; font-weight: bold; font-size: 18px; }
                .cta-button { display: inline-block; background: #3B82F6; color: white; padding: 12px 24px; text-decoration: none; border-radius: 6px; margin: 20px 0; }
                .footer { text-align: center; margin-top: 30px; color: #6b7280; font-size: 14px; }
            </style>
        </head>
        <body>
            <div class='container'>
                <div class='header'>
                    <div class='success-icon'>✓</div>
                    <h1>Payment Successful!</h1>
                    <p>Thank you for your purchase</p>
                </div>
                
                <div class='content'>
                    <p>Dear {$userName},</p>
                    
                    <p>Your payment has been successfully processed. Here are your order details:</p>
                    
                    <div class='order-details'>
                        <div class='order-row'>
                            <strong>Order ID:</strong>
                            <span>{$orderData['order_id']}</span>
                        </div>
                        <div class='order-row'>
                            <strong>Payment Date:</strong>
                            <span>" . date('F j, Y, g:i a') . "</span>
                        </div>
                        <div class='order-row'>
                            <strong>Payment Method:</strong>
                            <span>Online Payment</span>
                        </div>
                        <div class='order-row'>
                            <strong>Payment ID:</strong>
                            <span>{$orderData['payment_id']}</span>
                        </div>
                        <div class='order-row total'>
                            <strong>Total Amount:</strong>
                            <span>₹{$orderData['total_amount']}</span>
                        </div>
                    </div>
                    
                    <p><strong>What's Next?</strong></p>
                    <p>Your purchased notes are now available in your account. You can access them anytime from your dashboard.</p>
                    
                    <div style='text-align: center;'>
                        <a href='{$myNotesUrl}' class='cta-button'>View My Notes</a>
                    </div>
                    
                    <p><strong>Need Help?</strong></p>
                    <p>If you have any questions or need assistance, please don't hesitate to contact our support team.</p>
                    
                    <div class='footer'>
                        <p>Thank you for choosing StudyNotes!</p>
                        <p>This is an automated email. Please do not reply to this message.</p>
                    </div>
                </div>
            </div>
        </body>
        </html>";
    }
    
    /**
     * Get text template for payment confirmation
     */
    private function getPaymentConfirmationTextTemplate($userName, $orderData)
    {
        $baseUrl = $this->config['base_url'] ?? 'http://localhost:3000';
        $myNotesUrl = $baseUrl . '/my-notes';
        
        return "
Payment Confirmation - Order #{$orderData['order_id']}

Dear {$userName},

Your payment has been successfully processed. Here are your order details:

Order ID: {$orderData['order_id']}
Payment Date: " . date('F j, Y, g:i a') . "
Payment Method: Online Payment
Payment ID: {$orderData['payment_id']}
Total Amount: ₹{$orderData['total_amount']}

What's Next?
Your purchased notes are now available in your account. You can access them anytime from your dashboard.

View your notes: {$myNotesUrl}

Need Help?
If you have any questions or need assistance, please don't hesitate to contact our support team.

Thank you for choosing StudyNotes!

This is an automated email. Please do not reply to this message.";
    }
    
    /**
     * Send email using PHP's mail function
     */
    private function sendEmail($to, $subject, $htmlBody, $textBody)
    {
        $from = $this->config['email']['from'] ?? 'noreply@studynotes.com';
        $fromName = $this->config['email']['from_name'] ?? 'StudyNotes';
        
        // Always use SMTP when configured, otherwise fallback to mail()
        if (isset($this->config['email']['smtp_host']) && $this->config['email']['smtp_host'] !== 'localhost') {
            return $this->sendEmailViaSMTP($to, $subject, $htmlBody, $textBody, $from, $fromName);
        } else {
            // Fallback to basic mail() function
            $headers = [
                'MIME-Version: 1.0',
                'Content-Type: text/html; charset=UTF-8',
                'From: ' . $fromName . ' <' . $from . '>',
                'Reply-To: ' . $from,
                'X-Mailer: PHP/' . phpversion()
            ];
            
            $result = mail($to, $subject, $htmlBody, implode("\r\n", $headers));
            
            if (!$result) {
                error_log("Failed to send email to: {$to}");
                return false;
            }
            
            error_log("Email sent successfully to: {$to}");
            return true;
        }
    }
    
    private function sendEmailViaSMTP($to, $subject, $htmlBody, $textBody, $from, $fromName)
    {
        $smtpHost = $this->config['email']['smtp_host'] ?? 'smtp.gmail.com';
        $smtpPort = $this->config['email']['smtp_port'] ?? 587;
        $smtpUsername = $this->config['email']['smtp_username'] ?? '';
        $smtpPassword = $this->config['email']['smtp_password'] ?? '';
        $smtpEncryption = $this->config['email']['smtp_encryption'] ?? 'tls';
        
        try {
            // Create PHPMailer instance
            $mail = new PHPMailer(true);
            
            // Server settings
            $mail->isSMTP();
            $mail->Host = $smtpHost;
            $mail->SMTPAuth = true;
            $mail->Username = $smtpUsername;
            $mail->Password = $smtpPassword;
            $mail->SMTPSecure = $smtpEncryption;
            $mail->Port = $smtpPort;
            
            // Enable debug output in development
            if ($this->config['APP_DEBUG'] ?? false) {
                $mail->SMTPDebug = SMTP::DEBUG_SERVER;
                $mail->Debugoutput = function($str, $level) {
                    error_log("PHPMailer Debug: {$str}");
                };
            }
            
            // Recipients
            $mail->setFrom($from, $fromName);
            $mail->addAddress($to);
            
            // Content
            $mail->isHTML(true);
            $mail->Subject = $subject;
            $mail->Body = $htmlBody;
            $mail->AltBody = $textBody;
            
            // Send email
            $mail->send();
            
            error_log("Email sent successfully via SMTP to: {$to}");
            return true;
            
        } catch (Exception $e) {
            error_log("PHPMailer Error: " . $mail->ErrorInfo);
            return false;
        }
    }
    
    /**
     * Get user email from database by user_id
     */
    public function getUserEmail($userId)
    {
        try {
            $pdo = \Helpers\Database::getConnection();
            $stmt = pg_query_params($pdo, 'SELECT email FROM users WHERE id = $1', [$userId]);
            $result = pg_fetch_assoc($stmt);
            
            return $result ? $result['email'] : null;
        } catch (\Exception $e) {
            error_log("Error fetching user email: " . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Get user name from database by user_id
     */
    public function getUserName($userId)
    {
        try {
            $pdo = \Helpers\Database::getConnection();
            $stmt = pg_query_params($pdo, 'SELECT name FROM users WHERE id = $1', [$userId]);
            $result = pg_fetch_assoc($stmt);
            
            if ($result) {
                $firstName = $result['name'] ?? 'User';
                return $firstName ?: 'User';
            }
            
            return 'User';
        } catch (\Exception $e) {
            error_log("Error fetching user name: " . $e->getMessage());
            return 'User';
        }
    }
    
    /**
     * Send email verification code
     */
    public function sendVerificationEmail($userEmail, $userName, $verificationCode)
    {
        $subject = "Verify Your Email - StudyNotes";
        
        $htmlBody = $this->getVerificationEmailTemplate($userName, $verificationCode);
        $textBody = $this->getVerificationEmailTextTemplate($userName, $verificationCode);
        
        return $this->sendEmail($userEmail, $subject, $htmlBody, $textBody);
    }
    
    /**
     * Get HTML template for email verification
     */
    private function getVerificationEmailTemplate($userName, $verificationCode)
    {
        $baseUrl = $this->config['base_url'] ?? 'http://localhost:3000';
        
        return "
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset='utf-8'>
            <meta name='viewport' content='width=device-width, initial-scale=1.0'>
            <title>Verify Your Email</title>
            <style>
                body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                .header { background: #3B82F6; color: white; padding: 30px; text-align: center; border-radius: 8px 8px 0 0; }
                .content { background: #f9fafb; padding: 30px; border-radius: 0 0 8px 8px; }
                .verification-code { background: #e5e7eb; padding: 20px; text-align: center; border-radius: 8px; margin: 20px 0; font-size: 32px; font-weight: bold; letter-spacing: 4px; }
                .cta-button { display: inline-block; background: #3B82F6; color: white; padding: 12px 24px; text-decoration: none; border-radius: 6px; margin: 20px 0; }
                .footer { text-align: center; margin-top: 30px; color: #6b7280; font-size: 14px; }
            </style>
        </head>
        <body>
            <div class='container'>
                <div class='header'>
                    <h1>Verify Your Email</h1>
                    <p>Welcome to StudyNotes!</p>
                </div>
                
                <div class='content'>
                    <p>Dear {$userName},</p>
                    
                    <p>Thank you for registering with StudyNotes! To complete your registration, please verify your email address by entering the verification code below:</p>
                    
                    <div class='verification-code'>
                        {$verificationCode}
                    </div>
                    
                    <p><strong>Important:</strong></p>
                    <ul>
                        <li>This code will expire in 10 minutes</li>
                        <li>If you didn't create an account, you can safely ignore this email</li>
                        <li>For security reasons, never share this code with anyone</li>
                    </ul>
                    
                    <div style='text-align: center;'>
                        <a href='{$baseUrl}/verify-email' class='cta-button'>Verify Email</a>
                    </div>
                    
                    <p><strong>Need Help?</strong></p>
                    <p>If you have any questions or need assistance, please don't hesitate to contact our support team.</p>
                    
                    <div class='footer'>
                        <p>Thank you for choosing StudyNotes!</p>
                        <p>This is an automated email. Please do not reply to this message.</p>
                    </div>
                </div>
            </div>
        </body>
        </html>";
    }
    
    /**
     * Get text template for email verification
     */
    private function getVerificationEmailTextTemplate($userName, $verificationCode)
    {
        $baseUrl = $this->config['base_url'] ?? 'http://localhost:3000';
        
        return "
Verify Your Email - StudyNotes

Dear {$userName},

Thank you for registering with StudyNotes! To complete your registration, please verify your email address by entering the verification code below:

VERIFICATION CODE: {$verificationCode}

Important:
- This code will expire in 10 minutes
- If you didn't create an account, you can safely ignore this email
- For security reasons, never share this code with anyone

Verify your email: {$baseUrl}/verify-email

Need Help?
If you have any questions or need assistance, please don't hesitate to contact our support team.

Thank you for choosing StudyNotes!

This is an automated email. Please do not reply to this message.";
    }
    
    /**
     * Send password reset email
     */
    public function sendPasswordResetEmail($userEmail, $userName, $resetToken)
    {
        $subject = "Reset Your Password - StudyNotes";
        
        $htmlBody = $this->getPasswordResetTemplate($userName, $resetToken);
        $textBody = $this->getPasswordResetTextTemplate($userName, $resetToken);
        
        return $this->sendEmail($userEmail, $subject, $htmlBody, $textBody);
    }
    
    /**
     * Get HTML template for password reset
     */
    private function getPasswordResetTemplate($userName, $resetToken)
    {
        $baseUrl = $this->config['base_url'] ?? 'http://localhost:3000';
        $resetUrl = $baseUrl . '/reset-password?token=' . urlencode($resetToken);
        
        return "
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset='utf-8'>
            <meta name='viewport' content='width=device-width, initial-scale=1.0'>
            <title>Reset Your Password</title>
            <style>
                body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                .header { background: #3B82F6; color: white; padding: 30px; text-align: center; border-radius: 8px 8px 0 0; }
                .content { background: #f9fafb; padding: 30px; border-radius: 0 0 8px 8px; }
                .cta-button { display: inline-block; background: #3B82F6; color: white; padding: 12px 24px; text-decoration: none; border-radius: 6px; margin: 20px 0; }
                .footer { text-align: center; margin-top: 30px; color: #6b7280; font-size: 14px; }
                .warning { background: #fef3c7; border: 1px solid #f59e0b; padding: 15px; border-radius: 6px; margin: 20px 0; }
            </style>
        </head>
        <body>
            <div class='container'>
                <div class='header'>
                    <h1>Reset Your Password</h1>
                    <p>StudyNotes Account Recovery</p>
                </div>
                
                <div class='content'>
                    <p>Dear {$userName},</p>
                    
                    <p>We received a request to reset your password for your StudyNotes account. Click the button below to create a new password:</p>
                    
                    <div style='text-align: center;'>
                        <a href='{$resetUrl}' class='cta-button'>Reset Password</a>
                    </div>
                    
                    <div class='warning'>
                        <p><strong>Important:</strong></p>
                        <ul>
                            <li>This link will expire in 1 hour</li>
                            <li>If you didn't request a password reset, you can safely ignore this email</li>
                            <li>For security reasons, never share this link with anyone</li>
                        </ul>
                    </div>
                    
                    <p><strong>Need Help?</strong></p>
                    <p>If you have any questions or need assistance, please don't hesitate to contact our support team.</p>
                    
                    <div class='footer'>
                        <p>Thank you for choosing StudyNotes!</p>
                        <p>This is an automated email. Please do not reply to this message.</p>
                    </div>
                </div>
            </div>
        </body>
        </html>";
    }
    
    /**
     * Get text template for password reset
     */
    private function getPasswordResetTextTemplate($userName, $resetToken)
    {
        $baseUrl = $this->config['base_url'] ?? 'http://localhost:3000';
        $resetUrl = $baseUrl . '/reset-password?token=' . urlencode($resetToken);
        
        return "
Reset Your Password - StudyNotes

Dear {$userName},

We received a request to reset your password for your StudyNotes account. Click the link below to create a new password:

{$resetUrl}

Important:
- This link will expire in 1 hour
- If you didn't request a password reset, you can safely ignore this email
- For security reasons, never share this link with anyone

Need Help?
If you have any questions or need assistance, please don't hesitate to contact our support team.

Thank you for choosing StudyNotes!

This is an automated email. Please do not reply to this message.";
    }
} 