@extends('frontend.layouts.app')

@section('styles')

@endsection

@section('content')

    <section class="section">
        <div class="container">
            <div class="row">

                <div class="col s12 m8">
                    <div class="contact-content">
                        <h4 class="contact-title">Contactez-nous</h4>

                        <form id="contact-us" action="" method="POST">
                            @csrf
                            <input type="hidden" name="mailto" value="{{ $contactsettings[0]['email'] ?? 'p4alam@gmail.com' }}">

                            @auth
                                <input type="hidden" name="user_id" value="{{ auth()->id() }}">
                            @endauth

                            @auth
                                <div class="input-field col s12">
                                    <i class="material-icons prefix">person</i>
                                    <input id="name" name="name" type="text" class="validate" value="{{ auth()->user()->name }}" readonly>
                                    <label for="name">Nom</label>
                                </div>
                            @endauth
                            @guest
                                <div class="input-field col s12">
                                    <i class="material-icons prefix">person</i>
                                    <input id="name" name="name" type="text" class="validate">
                                    <label for="name">Nom</label>
                                </div>
                            @endguest

                            @auth
                                <div class="input-field col s12">
                                    <i class="material-icons prefix">mail</i>
                                    <input id="email" name="email" type="email" class="validate" value="{{ auth()->user()->email }}" readonly>
                                    <label for="email">E-mail</label>
                                </div>
                            @endauth
                            @guest
                                <div class="input-field col s12">
                                    <i class="material-icons prefix">mail</i>
                                    <input id="email" name="email" type="email" class="validate">
                                    <label for="email">E-mail</label>
                                </div>
                            @endguest

                            <div class="input-field col s12">
                                <i class="material-icons prefix">phone</i>
                                <input id="phone" name="phone" type="number" class="validate">
                                <label for="phone">Téléphone</label>
                            </div>

                            <div class="input-field col s12">
                                <i class="material-icons prefix">mode_edit</i>
                                <textarea id="message" name="message" class="materialize-textarea"></textarea>
                                <label for="message">Message</label>
                            </div>
                            
                            <button id="msgsubmitbtn" class="btn waves-effect waves-light indigo darken-4 btn-large" type="submit">
                                <span>ENVOYER</span>
                                <i class="material-icons right">send</i>
                            </button>

                        </form>

                    </div>
                </div> <!-- /.col -->

                <div class="col s12 m4">
                    <div class="contact-sidebar">
                        <div class="m-t-30">
                            <i class="material-icons left">call</i>
                            <h6 class="uppercase">Appelez-nous</h6>
                            @if(isset($contactsettings[0]) && $contactsettings[0]['phone'])
                                <h6 class="bold m-l-40">{{ $contactsettings[0]['phone'] }}</h6>
                            @endif
                        </div>
                        <div class="m-t-30">
                            <i class="material-icons left">mail</i>
                            <h6 class="uppercase">Adresse e-mail</h6>
                            @if(isset($contactsettings[0]) && $contactsettings[0]['email'])
                                <h6 class="bold m-l-40">{{ $contactsettings[0]['email'] }}</h6>
                            @endif
                        </div>
                        <div class="m-t-30">
                            <i class="material-icons left">map</i>
                            <h6 class="uppercase">Adresse</h6>
                            @if(isset($contactsettings[0]) && $contactsettings[0]['address'])
                                <h6 class="bold m-l-40">{!! $contactsettings[0]['address'] !!}</h6>
                            @endif
                        </div>
                    </div>
                </div>

            </div>
        </div>
    </section>

@endsection

@section('scripts')
    <script>
        $('textarea#message').characterCounter();

        $(function(){
            $(document).on('submit','#contact-us',function(e){
                e.preventDefault();

                var data = $(this).serialize();
                var url = "{{ route('contact.message') }}";
                var btn = $('#msgsubmitbtn');

                $.ajax({
                    type: 'POST',
                    url: url,
                    data: data,
                    beforeSend: function() {
                        $(btn).addClass('disabled');
                        $(btn).empty().append('<span>CHARGEMENT...</span><i class="material-icons right">rotate_right</i>');
                    },
                    success: function(data) {
                        if (data.message) {
                            M.toast({html: data.message, classes:'green darken-4'})
                        }
                    },
                    error: function(xhr) {
                        M.toast({html: 'ERREUR : Impossible d'envoyer le message !', classes: 'red darken-4'})
                    },
                    complete: function() {
                        $('form#contact-us')[0].reset();
                        $(btn).removeClass('disabled');
                        $(btn).empty().append('<span>ENVOYER</span><i class="material-icons right">send</i>');
                    },
                    dataType: 'json'
                });

            })
        })

    </script>
@endsection