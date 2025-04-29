// When the window has finished loading create our google map below
google.maps.event.addDomListener(window, 'load', init);

function init() {

    const citymap = {
        chicago: {
          center: { lat: 41.878, lng: -87.629 },
          population: 2714856,
        },
        newyork: {
          center: { lat: 40.714, lng: -74.005 },
          population: 8405837,
        },
        losangeles: {
          center: { lat: 34.052, lng: -118.243 },
          population: 3857799,
        },
        vancouver: {
          center: { lat: 49.25, lng: -123.1 },
          population: 603502,
        },
    };
    // Basic options for a simple Google Map
    // For more options see: https://developers.google.com/maps/documentation/javascript/reference#MapOptions
    var mapOptions = {
        // How zoomed in you want the map to start at (always required)
        zoom: 14,

        // The latitude and longitude to center the map (always required)
        center: new google.maps.LatLng(-1.2936290729512252, 36.81134053509082), // Nairobi

        // How you would like to style the map. 
        // This is where you would paste any style found on Snazzy Maps.
        styles: []
    };

    // Get the HTML DOM element that will contain your map 
    // We are using a div with id="gMap" seen below in the <body>
    var mapElement = document.getElementById('siteMap');

    var map = new google.maps.Map(mapElement, mapOptions);

    // Let's also add a marker while we're at it
    // marker = new google.maps.Marker({
    //     map: map,
    //     draggable: true,
    //     animation: google.maps.Animation.DROP,
    //     position: new google.maps.LatLng(-1.2936290729512252, 36.81134053509082),
    //     // Change those co-ordinates to yours, to change your location with given location.
    //     icon: '' // null = default icon
    // });

    for (const city in citymap) {
        // Add the circle for this city to the map.
        const cityCircle = new google.maps.Circle({
          strokeColor: "#FF0000",
          strokeOpacity: 0.8,
          strokeWeight: 2,
          fillColor: "#FF0000",
          fillOpacity: 0.35,
          map,
          center: citymap[city].center,
          radius: Math.sqrt(citymap[city].population) * 100,
    });
}
}