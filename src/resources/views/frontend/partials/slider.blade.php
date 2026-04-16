<section class="carousel carousel-slider center">
    @if($sliders)
        @foreach($sliders as $slider)
            <div class="carousel-item" style="background-image: url({{Storage::url('slider/'.$slider->image)}})" href="#{{$slider->id}}!">
            <div class="overlay"></div> <!-- Dimming overlay -->    
            <div class="slider-content">
                    <h2 class="white-text">{{ $slider->title }}</h2>
                    <p class="white-text">{{ $slider->description }}</p>
                </div>
            </div>
        @endforeach
    @else 
        <div class="carousel-item amber indigo-text" style="background-image: url({{ asset('frontend/images/real_estate.jpg') }})" href="#1!">
            <h2>Slider Title goes here</h2>
            <p class="indigo-text">Slider description goes here</p>
        </div>
    @endif
</section>
<!-- Vist codeastro.com for more projects -->

<style>
    .carousel-item {
    position: relative;
    background-size: cover;
    background-position: center center;
    height: 500px; /* or any height you prefer */
}

.carousel-item .overlay {
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: rgba(0, 0, 0, 0.4); /* Dim the image with 40% opacity black overlay */
    z-index: 1;
}

.carousel-item .slider-content {
    position: relative;
    z-index: 2; /* Ensures content is on top of the overlay */
    padding: 20px;
    text-align: center;
}

.carousel-item h2, .carousel-item p {
    color: white;
}

</style>