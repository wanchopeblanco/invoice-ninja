<?php namespace App\Providers;

use Session;
use Auth;
use Utils;
use HTML;
use URL;
use Request;
use Validator;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider {

	/**
	 * Bootstrap any application services.
	 *
	 * @return void
	 */
	public function boot()
	{
        HTML::macro('nav_link', function($url, $text, $url2 = '', $extra = '') {
            $class = ( Request::is($url) || Request::is($url.'/*') || Request::is($url2.'/*') ) ? ' class="active"' : '';
            $title = ucwords(trans("texts.$text")) . Utils::getProLabel($text);
            return '<li'.$class.'><a href="'.URL::to($url).'" '.$extra.'>'.$title.'</a></li>';
        });

        HTML::macro('tab_link', function($url, $text, $active = false) {
            $class = $active ? ' class="active"' : '';
            return '<li'.$class.'><a href="'.URL::to($url).'" data-toggle="tab">'.$text.'</a></li>';
        });

        HTML::macro('menu_link', function($type) {
            $types = $type.'s';
            $Type = ucfirst($type);
            $Types = ucfirst($types);
            $class = ( Request::is($types) || Request::is('*'.$type.'*')) && !Request::is('*settings*') ? ' active' : '';

            $str = '<li class="dropdown '.$class.'">
                   <a href="'.URL::to($types).'" class="dropdown-toggle">'.trans("texts.$types").'</a>
                   <ul class="dropdown-menu" id="menu1">
                   <li><a href="'.URL::to($types.'/create').'">'.trans("texts.new_$type").'</a></li>';
            
            if ($type == ENTITY_INVOICE) {
                $str .= '<li class="divider"></li>
                         <li><a href="'.URL::to('recurring_invoices').'">'.trans("texts.recurring_invoices").'</a></li>
                         <li><a href="'.URL::to('recurring_invoices/create').'">'.trans("texts.new_recurring_invoice").'</a></li>';
                if (Auth::user()->isPro()) {
                    $str .= '<li class="divider"></li>
                            <li><a href="'.URL::to('quotes').'">'.trans("texts.quotes").'</a></li>
                            <li><a href="'.URL::to('quotes/create').'">'.trans("texts.new_quote").'</a></li>';
                }
            } else if ($type == ENTITY_CLIENT) {
                $str .= '<li class="divider"></li>
                        <li><a href="'.URL::to('credits').'">'.trans("texts.credits").'</a></li>
                        <li><a href="'.URL::to('credits/create').'">'.trans("texts.new_credit").'</a></li>';
            }

            $str .= '</ul>
                  </li>';

            return $str;
        });

        HTML::macro('image_data', function($imagePath) {
            return 'data:image/jpeg;base64,' . base64_encode(file_get_contents(public_path().'/'.$imagePath));
        });

        HTML::macro('flatButton', function($label, $color) {
            return '<input type="button" value="' . trans("texts.{$label}") . '" style="background-color:' . $color . ';border:0 none;border-radius:5px;padding:12px 40px;margin:0 6px;cursor:hand;display:inline-block;font-size:14px;color:#fff;text-transform:none;font-weight:bold;"/>';
        });

        HTML::macro('emailViewButton', function($link = '#', $entityType = ENTITY_INVOICE) {
            return view('partials.email_button')
                        ->with([
                            'link' => $link,
                            'field' => "view_{$entityType}",
                            'color' => '#0b4d78',
                        ])
                        ->render();
        });

        HTML::macro('emailPaymentButton', function($link = '#') {
            return view('partials.email_button')
                        ->with([
                            'link' => $link,
                            'field' => 'pay_now',
                            'color' => '#36c157',
                        ])
                        ->render();
        });

        HTML::macro('breadcrumbs', function() {
            $str = '<ol class="breadcrumb">';

            // Get the breadcrumbs by exploding the current path.
            $basePath = Utils::basePath();
            $parts = explode('?', isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : '');
            $path = $parts[0];

            if ($basePath != '/') {
                $path = str_replace($basePath, '', $path);
            }
            $crumbs = explode('/', $path);

            foreach ($crumbs as $key => $val) {
                if (is_numeric($val)) {
                    unset($crumbs[$key]);
                }
            }

            $crumbs = array_values($crumbs);
            for ($i=0; $i<count($crumbs); $i++) {
                $crumb = trim($crumbs[$i]);
                if (!$crumb) {
                    continue;
                }
                if ($crumb == 'company') {
                    return '';
                }
                $name = trans("texts.$crumb");
                if ($i==count($crumbs)-1) {
                    $str .= "<li class='active'>$name</li>";
                } else {
                    $str .= '<li>'.link_to($crumb, $name).'</li>';
                }
            }
            return $str . '</ol>';
        });

        Validator::extend('positive', function($attribute, $value, $parameters) {
            return Utils::parseFloat($value) >= 0;
        });

        Validator::extend('has_credit', function($attribute, $value, $parameters) {
            $publicClientId = $parameters[0];
            $amount = $parameters[1];

            $client = \App\Models\Client::scope($publicClientId)->firstOrFail();
            $credit = $client->getTotalCredit();

            return $credit >= $amount;
        });

        // check that the time log elements don't overlap
        Validator::extend('time_log', function($attribute, $value, $parameters) {
            $lastTime = 0;
            $value = json_decode($value);
            array_multisort($value);
            foreach ($value as $timeLog) {
                list($startTime, $endTime) = $timeLog;
                if (!$endTime) {
                    continue;
                }
                if ($startTime < $lastTime || $startTime > $endTime) {
                    return false;
                }
                if ($endTime < min($startTime, $lastTime)) {
                    return false;
                }
                $lastTime = max($lastTime, $endTime);
            }
            return true;
        });

        Validator::extend('less_than', function($attribute, $value, $parameters) {
            return floatval($value) <= floatval($parameters[0]);
        });

        Validator::replacer('less_than', function($message, $attribute, $rule, $parameters) {
            return str_replace(':value', $parameters[0], $message);
        });

        Validator::extend('has_counter', function($attribute, $value, $parameters) {
            return !$value || strstr($value, '{$counter}');
        });

        Validator::extend('valid_contacts', function($attribute, $value, $parameters) {
            foreach ($value as $contact) {
                $validator = Validator::make($contact, [
                        'email' => 'email|required_without:first_name',
                        'first_name' => 'required_without:email',
                    ]);
                if ($validator->fails()) {
                    return false;
                }
            }
            return true;
        });

        Validator::extend('valid_invoice_items', function($attribute, $value, $parameters) {
            $total = 0;
            foreach ($value as $item) {
                $total += $item['qty'] * $item['cost'];
            }
            return $total <= MAX_INVOICE_AMOUNT;
        });

	}

	/**
	 * Register any application services.
	 *
	 * This service provider is a great spot to register your various container
	 * bindings with the application. As you can see, we are registering our
	 * "Registrar" implementation here. You can add your own bindings too!
	 *
	 * @return void
	 */
	public function register()
	{
		$this->app->bind(
			'Illuminate\Contracts\Auth\Registrar',
			'App\Services\Registrar'
		);

        $this->app->bind(
            'App\Ninja\Import\DataImporterServiceInterface',
            'App\Ninja\Import\FreshBooks\FreshBooksDataImporterService'
        );
	}

}
