<?php

declare(strict_types=1);

namespace SciRelais\Laravel\Tests;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Orchestra\Testbench\TestCase;
use RuntimeException;
use SciRelais\Laravel\Events\ShipmentStatusChanged;
use SciRelais\Laravel\Events\WebhookReceived;
use SciRelais\Laravel\Exceptions\SciConnectionException;
use SciRelais\Laravel\Exceptions\SciException;
use SciRelais\Laravel\Facades\Sci;
use SciRelais\Laravel\SciClient;
use SciRelais\Laravel\SciServiceProvider;

final class SciTest extends TestCase
{
    protected function getPackageProviders($app): array
    {
        return [SciServiceProvider::class];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('sci.url', 'https://sci.test/');
        $app['config']->set('sci.key', '1|cle');
        $app['config']->set('sci.webhook_secret', 'secret');
    }

    private function signed(string $body, ?int $time = null, string $secret = 'secret'): array
    {
        $time ??= time();

        return ['HTTP_X-Sci-Signature' => "t={$time},v1=".hash_hmac('sha256', $time.'.'.$body, $secret), 'CONTENT_TYPE' => 'application/json'];
    }

    public function test_cree_un_envoi_avec_la_cle_et_renvoie_data(): void
    {
        Http::fake(['sci.test/api/v1/shipments' => Http::response(['data' => ['tracking_code' => 'SR-1']], 201)]);

        $this->assertSame('SR-1', Sci::createShipment(['reference' => 'A'])['tracking_code']);

        Http::assertSent(fn (Request $r): bool => $r->url() === 'https://sci.test/api/v1/shipments'
            && $r->hasHeader('Authorization', 'Bearer 1|cle') && $r['reference'] === 'A' && $r->hasHeader('Accept', 'application/json'));
    }

    public function test_les_autres_routes(): void
    {
        Http::fake(['*' => Http::response(['data' => ['ok' => true]])]);

        Sci::relays('Moundou', 'all');
        Sci::quote(['weight_g' => 1]);
        Sci::shipment('SR 1');
        Sci::cancelShipment('SR-1');
        Sci::createReturn('SR-1');

        $sent = Http::recorded()->map(fn (array $pair): string => $pair[0]->method().' '.$pair[0]->url())->all();
        $this->assertSame([
            'GET https://sci.test/api/v1/relays?city=Moundou&country=all',
            'POST https://sci.test/api/v1/quotes',
            'GET https://sci.test/api/v1/shipments/SR%201',
            'DELETE https://sci.test/api/v1/shipments/SR-1',
            'POST https://sci.test/api/v1/shipments/SR-1/return',
        ], $sent);
    }

    public function test_un_refus_donne_son_code_stable(): void
    {
        Http::fake(['*' => Http::response(['error' => ['code' => 'insufficient_credit', 'message' => 'Crédit insuffisant']], 402)]);

        try {
            Sci::createShipment([]);
            $this->fail('exception attendue');
        } catch (SciException $e) {
            $this->assertSame(['insufficient_credit', 402, 'Crédit insuffisant'], [$e->errorCode, $e->status, $e->getMessage()]);
        }
    }

    public function test_une_erreur_de_validation_donne_les_champs(): void
    {
        Http::fake(['*' => Http::response(['message' => 'Invalide', 'errors' => ['weight_g' => ['requis']]], 422)]);

        try {
            Sci::quote([]);
            $this->fail('exception attendue');
        } catch (SciException $e) {
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
            Sci::createShipment(['weight_g' => 1]);
            $this->fail('exception attendue');
        } catch (SciConnectionException $e) {
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

            return Http::response(['data' => ['tracking_code' => 'SR-2']], 201);
        }]);

        $this->assertSame('SR-2', Sci::createShipment(['reference' => 'A'])['tracking_code']);
        $this->assertSame(2, $calls);
    }

    public function test_sans_configuration_le_message_dit_quoi_faire(): void
    {
        config(['sci.key' => null]);
        $this->app->forgetInstance(SciClient::class);
        Sci::clearResolvedInstances();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('SCI_KEY');
        Sci::relays();
    }

    public function test_notification_signee_declenche_les_evenements(): void
    {
        Event::fake();
        $body = json_encode(['id' => 'n1', 'type' => 'shipment.status_changed', 'occurred_at' => '2027-03-10T09:30:00+00:00', 'data' => ['tracking_code' => 'SR-1', 'reference' => 'A', 'status' => 'arrived']]);

        $this->call('POST', '/sci/webhook', [], [], [], $this->signed($body), $body)->assertNoContent();

        Event::assertDispatched(ShipmentStatusChanged::class, fn ($e): bool => $e->trackingCode === 'SR-1' && $e->status === 'arrived' && $e->reference === 'A' && $e->id === 'n1');
        Event::assertDispatched(WebhookReceived::class);
    }

    public function test_notification_refusee_si_fausse_absente_ou_ancienne(): void
    {
        Event::fake();
        $body = '{"id":"n1","type":"shipment.status_changed"}';

        $this->call('POST', '/sci/webhook', [], [], [], $this->signed($body, secret: 'autre'), $body)->assertUnauthorized();
        $this->call('POST', '/sci/webhook', [], [], [], ['CONTENT_TYPE' => 'application/json'], $body)->assertUnauthorized();
        $this->call('POST', '/sci/webhook', [], [], [], $this->signed($body, time() - 3600), $body)->assertUnauthorized();
        $this->call('POST', '/sci/webhook', [], [], [], $this->signed($body), $body.' ')->assertUnauthorized();

        Event::assertNotDispatched(WebhookReceived::class);
        Event::assertNotDispatched(ShipmentStatusChanged::class);
    }

    public function test_verification_de_l_installation(): void
    {
        Http::fake(['*' => Http::response(['data' => [['id' => 1], ['id' => 2]]])]);
        $this->artisan('sci:check')->expectsOutputToContain('2 relais')->assertSuccessful();
    }

    public function test_verification_d_une_cle_refusee(): void
    {
        Http::fake(['*' => Http::response(['message' => 'Unauthenticated.'], 401)]);

        $this->artisan('sci:check')->assertFailed();
    }
}
