<?php

namespace App\Services;

use App\Models\Order;
use App\Models\ProfessionalOrder;
use App\Models\ResellerRequest;
use App\Models\User;
use App\Notifications\AchatHubTransactionalNotification;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Throwable;

class TransactionalMailer
{
    public function isConfigured(): bool
    {
        $mailer = config('mail.default');

        if (in_array($mailer, ['array', 'log'], true)) {
            return true;
        }

        if ($mailer !== 'smtp') {
            return true;
        }

        return filled(config('mail.mailers.smtp.url'))
            || (
                filled(config('mail.mailers.smtp.host'))
                && filled(config('mail.mailers.smtp.username'))
                && filled(config('mail.mailers.smtp.password'))
            );
    }

    public function verification(User $user): void
    {
        if (! $this->isConfigured()) {
            Log::warning('Vérification e-mail AchatHub ignorée : transport e-mail non configuré.', [
                'user_id' => $user->id,
            ]);

            return;
        }

        try {
            $user->sendEmailVerificationNotification();
        } catch (Throwable $exception) {
            Log::error('Échec d’envoi de la vérification e-mail AchatHub.', [
                'user_id' => $user->id,
                'exception' => $exception->getMessage(),
            ]);
        }
    }

    public function customerCreated(User $user): void
    {
        $this->send($user, new AchatHubTransactionalNotification(
            'Bienvenue sur AchatHub',
            'Bienvenue '.$user->name.' !',
            [
                'Votre compte client est créé.',
                'Vous pouvez maintenant suivre vos commandes, enregistrer vos favoris et gérer vos informations.',
            ],
            'Ouvrir mon espace client',
            route('account.index'),
        ));
    }

    public function professionalApplicationCreated(User $user, ResellerRequest $application): void
    {
        $this->send($user, new AchatHubTransactionalNotification(
            'Votre demande AchatHub Pro est enregistrée',
            'Bonjour '.$user->name.',',
            [
                'Le dossier de '.$application->business_name.' a bien été reçu.',
                'Référence entreprise : SIRET '.$application->siret.'.',
                'Notre équipe vous informera par e-mail dès que la vérification sera terminée.',
            ],
            'Suivre mon dossier professionnel',
            route('reseller.dashboard'),
        ));
    }

    public function professionalApplicationReviewed(ResellerRequest $application): void
    {
        $status = $application->status;
        $approved = $status === 'Approuvée';

        $this->send($application->user, new AchatHubTransactionalNotification(
            'Mise à jour de votre accès AchatHub Pro',
            'Bonjour '.$application->user->name.',',
            [
                'Le statut de votre dossier professionnel est maintenant : '.$status.'.',
                $approved
                    ? 'Votre catalogue grossiste, vos présentoirs et vos commandes professionnelles sont accessibles.'
                    : ($application->admin_notes ?: 'Vous pouvez consulter votre espace pour obtenir davantage d’informations.'),
            ],
            $approved ? 'Accéder au catalogue Pro' : 'Consulter mon dossier',
            $approved ? route('pro.index') : route('reseller.dashboard'),
        ));
    }

    public function orderCreated(Order $order): void
    {
        $recipient = $order->user ?: Notification::route('mail', $order->guest_email);
        $url = $order->user
            ? route('account.order', $order)
            : route('orders.guest.show', ['order' => $order->access_token]);

        $this->send($recipient, new AchatHubTransactionalNotification(
            'Confirmation de commande '.$order->number,
            'Commande confirmée',
            [
                'Nous avons enregistré votre commande '.$order->number.'.',
                'Montant total : '.number_format((float) $order->total, 2, ',', ' ').' € TTC.',
                'Paiement : '.$order->payment_status.'.',
            ],
            'Suivre ma commande et ma facture',
            $url,
        ));
    }

    public function orderUpdated(Order $order): void
    {
        $recipient = $order->user ?: Notification::route('mail', $order->guest_email);
        $url = $order->user
            ? route('account.order', $order)
            : route('orders.guest.show', ['order' => $order->access_token]);

        $lines = [
            'Votre commande '.$order->number.' est maintenant : '.$order->status.'.',
            'Statut du paiement : '.$order->payment_status.'.',
        ];
        if ($order->tracking_number) {
            $lines[] = 'Suivi '.$order->carrier.' : '.$order->tracking_number.'.';
        }

        $this->send($recipient, new AchatHubTransactionalNotification(
            'Suivi de commande '.$order->number,
            'Votre commande évolue',
            $lines,
            'Consulter le suivi',
            $url,
        ));
    }

    public function professionalOrderCreated(ProfessionalOrder $order): void
    {
        $this->send($order->user, new AchatHubTransactionalNotification(
            'Commande professionnelle '.$order->number,
            'Commande AchatHub Pro confirmée',
            [
                'Votre commande professionnelle '.$order->number.' est enregistrée.',
                'Total : '.number_format((float) $order->total_ttc, 2, ',', ' ').' € TTC.',
                'Mode de paiement : '.$order->payment_method.'.',
                'Votre facture est disponible dans votre espace professionnel.',
            ],
            'Voir ma commande et ma facture',
            route('pro.invoice', $order),
        ));
    }

    public function professionalOrderUpdated(ProfessionalOrder $order): void
    {
        $this->send($order->user, new AchatHubTransactionalNotification(
            'Suivi professionnel '.$order->number,
            'Mise à jour de votre commande Pro',
            [
                'Statut de la commande : '.$order->status.'.',
                'Statut du paiement : '.$order->payment_status.'.',
            ],
            'Ouvrir ma facture Pro',
            route('pro.invoice', $order),
        ));
    }

    private function send(User|AnonymousNotifiable|null $recipient, AchatHubTransactionalNotification $notification): void
    {
        if (! $recipient || ! $this->isConfigured()) {
            if ($recipient && ! $this->isConfigured()) {
                Log::warning('E-mail transactionnel AchatHub ignoré : transport e-mail non configuré.', [
                    'subject' => $notification->subject,
                ]);
            }

            return;
        }

        try {
            $recipient->notify($notification);
        } catch (Throwable $exception) {
            Log::error('Échec d’envoi d’un e-mail transactionnel AchatHub.', [
                'notification' => $notification::class,
                'subject' => $notification->subject,
                'exception' => $exception->getMessage(),
            ]);
        }
    }
}
