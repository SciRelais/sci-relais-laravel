<?php

declare(strict_types=1);

namespace ChariRelais\Laravel\Tests;

use ChariRelais\Laravel\ChariClient;
use ChariRelais\Laravel\ChariServiceProvider;
use ChariRelais\Laravel\Events\ShipmentStatusChanged;
use ChariRelais\Laravel\Events\WebhookReceived;
use ChariRelais\Laravel\Exceptions\ChariConnectionException;
use ChariRelais\Laravel\Exceptions\ChariException;
use ChariRelais\Laravel\Facades\Chari;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Orchestra\Testbench\TestCase;
use RuntimeException;

final class ChariTest extends TestCase
{
    protected function getPackageProviders($app): array
    {
        return [ChariServiceProvider::class];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('chari.url', 'https://chari.test/');
        $app['config']->set('chari.key', '1|cle');
        $app['config']->set('chari.webhook_secret', 'secret');
    }

    private function signed(string $body, ?int $time = null, string $secret = 'secret'): array
    {
        $time ??= time();

        return ['HTTP_X-Chari-Signature' => "t={$time},v1=".hash_hmac('sha256', $time.'.'.$body, $secret), 'CONTENT_TYPE' => 'application/json'];
    }

    public function test_cree_un_envoi_avec_la_cle_et_renvoie_data(): void
    {
        Http::fake(['chari.test/api/v1/shipments' => Http::response(['data' => ['tracking_code' => 'CR-1']], 201)]);

        $this->assertSame('CR-1', Chari::createShipment(['reference' => 'A'])['tracking_code']);

        Http::assertSent(fn (Request $r): bool => $r->url() === 'https://chari.test/api/v1/shipments'
            && $r->hasHeader('Authorization', 'Bearer 1|cle') && $r['reference'] === 'A' && $r->hasHeader('Accept', 'application/json'));
    }

    public function test_les_autres_routes(): void
    {
        Http::fake(['*' => Http::response(['data' => ['ok' => true]])]);

        Chari::relays('Moundou', 'all');
        Chari::quote(['weight_g' => 1]);
        Chari::shipment('CR 1');
        Chari::cancelShipment('CR-1');
        Chari::createReturn('CR-1');

        $sent = Http::recorded()->map(fn (array $pair): string => $pair[0]->method().' '.$pair[0]->url())->all();
        $this->assertSame([
            'GET https://chari.test/api/v1/relays?city=Moundou&country=all',
            'POST https://chari.test/api/v1/quotes',
            'GET https://chari.test/api/v1/shipments/CR%201',
            'DELETE https://chari.test/api/v1/shipments/CR-1',
            'POST https://chari.test/api/v1/shipments/CR-1/return',
        ], $sent);
    }

    public function test_un_refus_donne_son_code_stable(): void
    {
        Http::fake(['*' => Http::response(['error' => ['code' => 'insufficient_credit', 'message' => 'Crédit insuffisant']], 402)]);

        try {
            Chari::createShipment([]);
            $this->fail('exception attendue');
        } catch (ChariException $e) {
            $this->assertSame(['insufficient_credit', 402, 'Crédit insuffisant'], [$e->errorCode, $e->status, $e->getMessage()]);
        }
    }

    public function test_une_erreur_de_validation_donne_les_champs(): void
    {
        Http::fake(['*' => Http::response(['message' => 'Invalide', 'errors' => ['weight_g' => ['requis']]], 422)]);

        try {
            Chari::quote([]);
            $this->fail('exception attendue');
        } catch (ChariException $e) {
            $this->assertSame(['weight_g' => ['requis']], $e->errors);
        }
    }

    public function test_coupure_sans_reference_pas_de_reessai(): void
    {
        $calls = 0;
        Http::fake(['*' => function () use (&$calls) {
            $calls++;
            throw new ConnectionException('coupure');
        }]);

        try {
            Chari::createShipment(['weight_g' => 1]);
            $this->fail('exception attendue');
        } catch (ChariConnectionException $e) {
            $this->assertInstanceOf(ConnectionException::class, $e->getPrevious());
        }
        $this->assertSame(1, $calls);
    }

    public function test_coupure_avec_reference_nouvel_essai(): void
    {
        $calls = 0;
        Http::fake(['*' => function () use (&$calls) {
            $calls++;
            if ($calls === 1) {
                throw new ConnectionException('coupure');
            }

            return Http::response(['data' => ['tracking_code' => 'CR-2']], 201);
        }]);

        $this->assertSame('CR-2', Chari::createShipment(['reference' => 'A'])['tracking_code']);
        $this->assertSame(2, $calls);
    }

    public function test_sans_configuration_le_message_dit_quoi_faire(): void
    {
        config(['chari.key' => null]);
        $this->app->forgetInstance(ChariClient::class);
        Chari::clearResolvedInstances();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('CHARI_KEY');
        Chari::relays();
    }

    public function test_notification_signee_declenche_les_evenements(): void
    {
        Event::fake();
        $body = json_encode(['id' => 'n1', 'type' => 'shipment.status_changed', 'occurred_at' => '2027-03-10T09:30:00+00:00', 'data' => ['tracking_code' => 'CR-1', 'reference' => 'A', 'status' => 'arrived']]);

        $this->call('POST', '/chari/webhook', [], [], [], $this->signed($body), $body)->assertNoContent();

        Event::assertDispatched(ShipmentStatusChanged::class, fn ($e): bool => $e->trackingCode === 'CR-1' && $e->status === 'arrived' && $e->reference === 'A' && $e->id === 'n1');
        Event::assertDispatched(WebhookReceived::class);
    }

    public function test_notification_refusee_si_fausse_absente_ou_ancienne(): void
    {
        Event::fake();
        $body = '{"id":"n1","type":"shipment.status_changed"}';

        $this->call('POST', '/chari/webhook', [], [], [], $this->signed($body, secret: 'autre'), $body)->assertUnauthorized();
        $this->call('POST', '/chari/webhook', [], [], [], ['CONTENT_TYPE' => 'application/json'], $body)->assertUnauthorized();
        $this->call('POST', '/chari/webhook', [], [], [], $this->signed($body, time() - 3600), $body)->assertUnauthorized();
        $this->call('POST', '/chari/webhook', [], [], [], $this->signed($body), $body.' ')->assertUnauthorized();

        Event::assertNotDispatched(WebhookReceived::class);
        Event::assertNotDispatched(ShipmentStatusChanged::class);
    }

    public function test_verification_de_l_installation(): void
    {
        Http::fake(['*' => Http::response(['data' => [['id' => 1], ['id' => 2]]])]);
        $this->artisan('chari:check')->expectsOutputToContain('2 relais')->assertSuccessful();
    }

    public function test_verification_d_une_cle_refusee(): void
    {
        Http::fake(['*' => Http::response(['message' => 'Unauthenticated.'], 401)]);

        $this->artisan('chari:check')->assertFailed();
    }
}
