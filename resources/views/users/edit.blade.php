@extends('header')

@section('content') 
  @parent
  @include('accounts.nav', ['selected' => ACCOUNT_USER_MANAGEMENT])

  {!! Former::open($url)->method($method)->addClass('warn-on-exit')->rules(array(
      'first_name' => 'required',
      'last_name' => 'required',
      'email' => 'required|email',
  )); !!}

  @if ($user)
    {!! Former::populate($user) !!}    
  @endif

<div class="panel panel-default">
<div class="panel-heading">
    <h3 class="panel-title">{!! $title !!}</h3>
</div>
<div class="panel-body form-padding-right">

  {!! Former::text('first_name') !!}
  {!! Former::text('last_name') !!}
  {!! Former::text('email') !!}

</div>
</div>

  {!! Former::actions( 
      Button::normal(trans('texts.cancel'))->asLinkTo(URL::to('/settings/user_management'))->appendIcon(Icon::create('remove-circle'))->large(),
      Button::success(trans($user && $user->confirmed ? 'texts.save' : 'texts.send_invite'))->submit()->large()->appendIcon(Icon::create($user && $user->confirmed ? 'floppy-disk' : 'send'))
  )!!}

  {!! Former::close() !!}

@stop

@section('onReady')
    $('#first_name').focus();
@stop