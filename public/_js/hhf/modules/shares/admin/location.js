(function() {
    var location = hh.namespace('hhf.modules.shares.admin.location');

    location.config = {
        map: null,
        longitudeDegrees: null,
        latitudeDegrees: null,
        address: null,
        city: null,
        state: null,
        country: null,
        zipCode: null,
        firstDraw: null,
        mapLoader: null,
        ckEditor: {
            customConfig : '',
            toolbar:
            [
                ['Bold', 'Italic', 'Underline', 'Strike'],
                [
                    'NumberedList', 'BulletedList', '-',
                    'Outdent', 'Indent', 'Blockquote'],
                ['Link', 'Unlink'],
                ['Cut', 'Copy', 'Paste', 'PasteText', 'PasteFromWord'],
                ['Undo', 'Redo'],
                ['RemoveFormat']
            ],
            colorButton_enableMore: false,
            disableNativeSpellChecker: false
        }
    };

    location.init = function(config) {
        $.extend(location.config, config);

        jQuery.validator.addMethod('time', function(value, element) {
            return this.optional(element)
                || ((Date.parse(value) != null) ? true : false);
        }, location.config.lang.timeError);

        $(document).ready(function () {location.documentInit()});
    };

    location.documentInit = function() {
    	if (location == undefined) location = this;
        location.config.longitudeDegrees = $('#longitudeDegrees');
        location.config.latitudeDegrees = $('#latitudeDegrees');
        location.config.address = $('#address');
        location.config.city = $('#city');
        location.config.state = $('#state');
        location.config.country = $('#country');
        location.config.zipCode = $('#zipCode');

        /*
        Use these change events if you want to fire constant lookups as the user changes the address.
        Currently there is a map click event in place that will refresh the location
        
        location.config.address.change(findLocation)
        location.config.city.change(findLocation);
        location.config.state.change(findLocation);
        location.config.zipCode.change(findLocation);
        
        */

        $('#city').autocomplete({
            source: function(request, response) {
                $.get(location.config.url,
                    {
                        country : $('#country').val(),
                        subdivision : $('#state').val(),
                        unlocode: request.term
                    },
                    function(unlocodes) {
                        response(unlocodes);
                    },
                    'json'
                );
            }
        });

        $('#details').ckeditor(
            function(){
                var editor = $('#details').ckeditorGet();
                editor.on( "blur", function() {
                    this.updateElement();
                });
            },
            location.config.ckEditor
        );

        $('#location').validate({
            rules: {
                'timeStart' : 'time',
                'timeEnd' : 'time'
            },
            messages : {
                'timeStart' : {
                    'time' : location.config.lang.timeError
                },
                'timeEnd' : {
                    'time' : location.config.lang.timeError
                }
            },
            errorContainer: $('#formError'),
            submitHandler: function(form) {
                $('#details').ckeditorGet().updateElement();

                var $addOnCutOffTime = $('#addOnCutOffTime'),
                    val = $addOnCutOffTime.val();

                if (!$.isNumeric(val) || val > 0) {

                    var timestamp = Date.parse(val);
                    if (timestamp !== null) {
                        $addOnCutOffTime.val(timestamp.toString('HH:mm'));
                    } else {
                        $addOnCutOffTime.val('');
                    }
                }
                form.submit();
            }
        });

        $('#timeStart').change(location.convertTime);

        $('#timeEnd').change(location.convertTime);

        $('#addOnCutOffTime').change(location.convertAddonTime);
        
        
        /*
         location.config.longitudeDegrees = $('#longitudeDegrees');
	        location.config.latitudeDegrees = $('#latitudeDegrees');
	        location.config.address = $('#address');
	        location.config.city = $('#city');
	        location.config.state = $('#state');
	        location.config.country = $('#country');
	        location.config.zipCode = $('#zipCode');
         
         * */
        
        var mapBox = $('#map');
        $(mapBox).css({height: 400,width:400});
        var mapEl = document.getElementById($(mapBox).attr('id'));
        
        var     zoom = 13,
                coords,
                map,
                sm = nokia.places.search.manager,
                resultSet,
                search,
                customMarker = 'http://www.'+location.config.rootDomain+'/_images/mapMarker.png',
                x,
                y;

        x = parseFloat(location.config.latitudeDegrees.val());
        y = parseFloat(location.config.longitudeDegrees.val());
        
       if (x && y) {
       		coords = [ x , y ];
       } else {
        	coords = [0,0];
       }
       
        map = new nokia.maps.map.Display(mapEl, {
       		zoomLevel: 13, 
       		center: coords,
        	components: [
        		new nokia.maps.map.component.Behavior(),
        		new nokia.maps.map.component.ZoomBar(),
        		new nokia.maps.map.component.ScaleBar()
        	]
       });
        
       
       map.addListener('displayready', function () {
       		if (!coords[0] && !coords[1]) {
       			findLocation();
       		} else {
       			showMarker(coords);
       		}
       });
       
       map.addListener('click', function () {
    	  findLocation();
       });
       
       function findLocation() {
		   search = buildLookup();
		   sm.geoCode({
				searchTerm: search,
				onComplete: processResults
		   });
       }
       
       function buildLookup() {
    	   var s = '';
    	   
    	   if (location.config.address && location.config.address.val()) {
    		   s = location.config.address.val(); 
    	   }
    	   
    	   if (location.config.city && location.config.city.val()) {
    		   s += ', ' + location.config.city.val();
    	   }
    	   
    	   if (location.config.state && location.config.state.val()) {
    		   s += ', ' +location.config.state.val();
    	   }
    	   
    	   if (location.config.country && location.config.country.val()) {
    		   s += ', ' + location.config.country.val();
    	   }
    	   
    	   if (location.config.zipcode && location.config.zipcode.val()) {
    		   s += ', '+ location.config.zipcode.val();
    	   }
    	   return s;
       }
       
       function processResults(data, status, rID) {
       		if (status == 'OK') {
       			location = data.location;
       			showMarker(location.position);
       			map.setCenter(location.position);
       			map.setZoomLevel(zoom);
       			// set the values of the inputs
       			$("#latitudeDegrees").val(location.position.latitude);
       			$("#longitudeDegrees").val(location.position.longitude);
       		}
       }
       
       function showMarker(position) {
    	   position = nokia.maps.geo.Coordinate.fromObject(position);
    	   if (resultSet) map.objects.remove(resultSet);
    	   resultSet = new nokia.maps.map.Container();
    	   var marker = new nokia.maps.map.Marker(position, {
						icon: customMarker,
						anchor: new nokia.maps.util.Point(0, 26)
					});
			resultSet.objects.add(marker);
			map.objects.add(resultSet);
			map.setCenter(position);
   			map.setZoomLevel(zoom);
       }
       
       
        /*
        location.config.map = new YMap(
            document.getElementById('map'),
            YAHOO_MAP_REG
        );
        location.config.map.addTypeControl();
        location.config.map.addPanControl();
        location.config.map.addZoomShort();
        location.config.map.removeZoomScale();
        location.config.map.disableKeyControls();

        var l = location.getMapLocation();
        location.config.map.drawZoomAndCenter(l.location, l.zoom);
        location.config.firstDraw = true;
        YEvent.Capture(
                location.config.map,
                EventsList.MouseClick,a
                location.reportPosition
        );
        YEvent.Capture(
            location.config.map,
            EventsList.endMapDraw,
            location.markPosition
        );
         */
        
        $('.tooltip').qtip({
           style: {
               classes: 'ui-tooltip-shadow ui-tooltip-rounded',
               widget: true
           },
           position: {
              my: 'bottom right',
              at: 'top center',
              method: 'flip'
           }
        });
    };

    location.convertAddonTime = function() {
        var $this = $(this),
            val = $this.val();

        if (!$.isNumeric(val) || val > 0) {
            if (!/[^0-9: ]/.test(val)) {
                val += 'am';
            }

            var timestamp = Date.parse(val);

            if (timestamp !== null) {
                $this.val(timestamp.toString('h:mm tt'));
            } else {
                $this.val('');
            }

        }
    };

    location.convertTime = function(val) {
        var $this = $(this);
        var val = $this.val();

        if (!/[^0-9: ]/.test(val)) {
            val += 'pm';
        }
        var timestamp = Date.parse(val);
        if (timestamp != null) {
            $this.val(timestamp.toString('h:mm tt'));
        }
    };
    
    location.addMarker = function(coords) {
        var myImage = new YImage();
        myImage.src = 'http://www.' +
            location.config.rootDomain + '/_images/mapMarker.png';
        myImage.size = new YSize(27,26);
        myImage.offsetSmartWindow = new YCoordPoint(0,0);

        var marker = new YMarker(coords, myImage);
        marker.setSmartWindowColor('black');
        marker.addAutoExpand($('#name').val());
        location.config.map.addOverlay(marker);
    };

    location.getMapLocation = function() {
        var result = {
            location : '',
            zoom : 5
        };

        if (location.config.longitudeDegrees.val()
            && location.config.longitudeDegrees.val().length
            && location.config.latitudeDegrees.val()
            && location.config.latitudeDegrees.val().length) {

            result.location = new YGeoPoint(
                location.config.latitudeDegrees.val(),
                location.config.longitudeDegrees.val()
            );
            location.addMarker(result.location);
            result.zoom = 2;
        } else {
            if (location.config.address.val().length) {
                result.location = location.config.address.val() + ', ' +
                    (location.config.city.val() || location.config.farmCity) + ', ' +
                    location.config.state.val() + ', '
                    + location.config.country.val();

                if (location.config.zipCode.val().length) {
                    result.location += ', ' + location.config.zipCode.val();
                }
                result.zoom = 3;
            } else if (location.config.city.val().length) {
                result.location = location.config.city.val() + ', ' +
                    location.config.state.val() + ', ' +
                    location.config.country.val();

                if (location.config.zipCode.val().length) {
                    result.location += ', ' + location.config.zipCode.val();
                }

                result.zoom = 4;
            } else {
                result.location = location.config.farmCity + ', ' +
                    location.config.state.val() + ', ' +
                    location.config.country.val();

                result.zoom = 6;
            }
        }

        return result;
    };

    location.reportPosition = function(event, coords) {
        location.config.map.removeMarkersAll();
        location.config.longitudeDegrees.val('');
        location.config.latitudeDegrees.val('');

        var currentGeoPoint = new YGeoPoint(coords.Lat, coords.Lon);
        location.addMarker(currentGeoPoint);
        location.config.longitudeDegrees.val(coords.Lon);
        location.config.latitudeDegrees.val(coords.Lat);
     };

     location.markPosition = function() {
         if (location.config.firstDraw) {
             location.config.firstDraw = false;
         } else {
             location.reportPosition('', location.config.map.getCenterLatLon());
         }
     };

    location.redrawMap = function () {
        location.config.longitudeDegrees.val('');
        location.config.latitudeDegrees.val('');

        var l = location.getMapLocation();
        location.config.map.drawZoomAndCenter(l.location, l.zoom);
    };

})();
