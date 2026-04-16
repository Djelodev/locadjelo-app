
<!-- SECTION RECHERCHE -->

<section class="blue-grey darken-3 white-text center">
    <div class="container">
        <div class="row m-b-0">
            <div class="col s12">
                <form action="{{ route('search')}} " method="GET">
                <h4>Recherchez votre bien</h4>
                    <div class="searchbar">
                        <div class="input-field col s12 m3">
                            <input type="text" name="city" id="autocomplete-input" class="autocomplete custominputbox" autocomplete="off">
                            <label for="autocomplete-input">Entrez une ville</label>
                        </div>
                        <div class="input-field col s12 m2">
                            <select name="type" class="browser-default">
                                <option value="" disabled selected>Type de bien</option>
                                <option value="apartment">Appartement</option>
                                <option value="house">Maison</option>
                            </select>
                        </div>
                        <div class="input-field col s12 m2">
                            <select name="purpose" class="browser-default">
                                <option value="" disabled selected>Objectif</option>
                                <option value="rent">Location</option>
                                <option value="sale">Vente</option>
                            </select>
                        </div>
                        <div class="input-field col s12 m2">
                            <select name="bedroom" class="browser-default">
                                <option value="" disabled selected>Chambres</option>
                                @if(isset($bedroomdistinct))
                                    @foreach($bedroomdistinct as $bedroom)
                                        <option value="{{$bedroom->bedroom}}">{{$bedroom->bedroom}}</option>
                                    @endforeach
                                @endif
                            </select>
                        </div>
                        <div class="input-field col s12 m2">
                            <input type="text" name="maxprice" id="maxprice" class="custominputbox">
                            <label for="maxprice">Prix maximum</label>
                        </div>
                        <div class="input-field col s12 m1">
                            <button class="btn btnsearch waves-effect waves-light w100" type="submit">
                                <i class="material-icons">search</i>
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
</section>
