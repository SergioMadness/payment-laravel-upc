<?php namespace professionalweb\payment;

use Illuminate\Support\ServiceProvider;
use professionalweb\payment\contracts\PayService;
use professionalweb\payment\drivers\upc\UpcDriver;
use professionalweb\payment\drivers\upc\UpcProtocol;
use professionalweb\payment\contracts\PaymentFacade;
use professionalweb\payment\interfaces\upc\UpcService;

/**
 * upc.ua payment provider
 * @package professionalweb\payment
 */
class UpcProvider extends ServiceProvider
{
    /**
     * Indicates if loading of the provider is deferred.
     *
     * @var bool
     */
    protected $defer = true;

    public function boot()
    {
        app(PaymentFacade::class)->registerDriver(UpcService::PAYMENT_UPC, UpcService::class);
    }

    /**
     * Bind two classes
     *
     * @return void
     */
    public function register()
    {
        $this->app->bind(UpcService::class, function ($app) {
            return (new UpcDriver(config('payment.upc')))->setTransport(
                new UpcProtocol(
                    config('payment.upc.url'),
                    config('payment.upc.merchantId'),
                    config('payment.upc.terminalId'),
                    config('payment.upc.pathToLocalKey'),
                    config('payment.upc.pathToUpcKey')
                )
            );
        });
        $this->app->bind(PayService::class, function ($app) {
            return (new UpcDriver(config('payment.upc')))->setTransport(
                new UpcProtocol(
                    config('payment.upc.url'),
                    config('payment.upc.merchantId'),
                    config('payment.upc.terminalId'),
                    config('payment.upc.pathToLocalKey'),
                    config('payment.upc.pathToUpcKey')
                )
            );
        });
        $this->app->bind(UpcDriver::class, function ($app) {
            return (new UpcDriver(config('payment.upc')))->setTransport(
                new UpcProtocol(
                    config('payment.upc.url'),
                    config('payment.upc.merchantId'),
                    config('payment.upc.terminalId'),
                    config('payment.upc.pathToLocalKey'),
                    config('payment.upc.pathToUpcKey')
                )
            );
        });
        $this->app->bind('\professionalweb\payment\Upc', function ($app) {
            return (new UpcDriver(config('payment.upc')))->setTransport(
                new UpcProtocol(
                    config('payment.upc.url'),
                    config('payment.upc.merchantId'),
                    config('payment.upc.terminalId'),
                    config('payment.upc.pathToLocalKey'),
                    config('payment.upc.pathToUpcKey')
                )
            );
        });
    }

    /**
     * Get the services provided by the provider.
     *
     * @return array
     */
    public function provides()
    {
        return [PayService::class, UpcDriver::class, '\professionalweb\payment\Upc'];
    }
}