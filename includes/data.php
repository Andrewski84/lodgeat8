<?php
declare(strict_types=1);

return [
    'site' => [
        'name' => 'Lodging at 8',
        'reservation_url' => 'https://reservations.cubilis.eu/lodging-at-8-leuven/Rooms/Select',
        'booking_url' => 'https://reservations.cubilis.eu/lodging-at-8-leuven?Language=nl-NL',
        'email' => 'info@lodgingat8.be',
        'phone' => '+32 (0) 475 93 72 21',
        'address' => 'Weldadigheidsstraat 8, 3000 Leuven',
        'owner' => 'Steven Frooninckx',
        'company' => 'B&B Lodging At 8 V.O.F.',
        'business_number' => 'BE 0898 536 239',
        'iban' => 'BE84-0682-4977-8259',
        'logo' => 'site/logo-footer.png',
    ],
    'booking_widget' => [
        'enabled' => true,
        'title' => 'Reservatie',
        'button_label' => 'Check availability',
        'embed_code' => <<<'HTML'
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml">

<head runat="server">
    <title></title>
    <link type="text/css" href="https://static.cubilis.eu/jquery/ui/smoothness/jquery-ui-1.8.16.custom.css" rel="Stylesheet" />
    <link type="text/css" href="https://static.cubilis.eu/fancybox/jquery.fancybox-1.3.4.css" rel="Stylesheet" />
    <link type="text/css" href="https://static.cubilis.eu/jquery/defaultView.css" rel="Stylesheet" />
    <script type="text/javascript" src="https://static.cubilis.eu/jquery/jquery-1.6.4.min.js"></script>
    <script type="text/javascript" src="https://static.cubilis.eu/jquery/jquery-ui-1.8.16.custom.min.js"></script>
    <script type="text/javascript" src="https://static.cubilis.eu/jquery/date.js"></script>

    <script type="text/javascript">
        var _TAAL = 'en';

        $(document).ready(function () {
            $("#startdate").datepicker({
                dateFormat: "dd-mm-yy", buttonImage: "https://static.cubilis.eu/images/datepicker_light.png",
                showOn: "both", buttonImageOnly: true, minDate: 0
            });
            $("#enddate").datepicker({
                dateFormat: "dd-mm-yy", buttonImage: "https://static.cubilis.eu/images/datepicker_light.png",
                showOn: "both", buttonImageOnly: true, minDate: 1
            });
            var today = new Date();
            $("#startdate").datepicker("setDate", new Date());
            var tomorrow = today.add(1).days();
            $("#enddate").datepicker("setDate", tomorrow);

            $("#startdate").change(function () {
                var d = Date.parseExact($(this).val(), "dd-MM-yyyy");
                d = d.add(1).days();
                $("#enddate").datepicker("option", "minDate", d);
            });
        });

        function fastbookerFormatDate(date) {
            var dateobj = Date.parseExact(date, "dd-MM-yyyy");
            return dateobj.getFullYear() + "-" + (dateobj.getMonth() + 1) + "-" + dateobj.getDate();
        }

        function submitmyform(form) {
            window.location = form.action + "?lang=" + _TAAL + "&Arrival=" + fastbookerFormatDate($("#startdate").val()) + "&Departure=" + fastbookerFormatDate($("#enddate").val());
            return false;
        }
    </script>
</head>

<body>
    <form method="get" action="https://bookingengine.mylighthouse.com/6471/Rooms/Select" onsubmit="return submitmyform(this);">
        <table id="CheckAvailabilityContainer" cellpadding="10">
            <tr>
                <td>
                    <input type="text" name="startdate" id="startdate" />
                </td>
            </tr>
            <tr>
                <td>
                    <input type="text" name="enddate" id="enddate" />
                </td>
            </tr>
            <tr>
                <td>
                    <input type="submit" class="btnCheckAvail" value="Check availability" id="btnSubmit" />
                </td>
            </tr>
        </table>
    </form>
</body>
</html>
HTML,
    ],
    'backgrounds' => [
        'home/bgstr-pickwick.jpg',
        'home/bgstr-ontbijtgranen.jpg',
        'kamer-1/bgstr-dakterras.jpg',
        'home/bgstr-raam.jpg',
        'home/bgstr-breakfast2.jpg',
        'kamer-2/bgstr-room2door.jpg',
        'kamer-1/bgstr-shower.jpg',
        'home/bgstr-breakfast.jpg',
        'leuven/bgstr-brochures.jpg',
        'kamer-2/bgstr-room2.jpg',
        'kamer-2/bgstr-room2-2.jpg',
        'leuven/bgstr-brochureleuven.jpg',
        'kamer-3/bgstr-bathroom.jpg',
        'home/bgstr-gevel1.jpg',
    ],
    'navigation' => [
        'leuven' => 'Leuven',
        'kamer-1' => 'Room 1',
        'kamer-2' => 'Room 2',
        'kamer-3' => 'Room 3',
        'locatie' => 'Location',
        'contact' => 'Contact',
        'links' => 'Links',
    ],
    'footer_navigation' => [
        'home' => 'Home',
        'voorwaarden' => 'Cancellation policy',
    ],
    'galleries' => [
        'home' => [
            ['file' => 'home/gevel2.jpg', 'alt' => 'Gevel van Lodging at 8'],
            ['file' => 'home/gevel-deur.jpg', 'alt' => 'Voordeur van Lodging at 8'],
            ['file' => 'home/breakfast-overv.jpg', 'alt' => 'Ontbijttafel'],
            ['file' => 'home/gevel-detailnummer.jpg', 'alt' => 'Huisnummer 8'],
            ['file' => 'home/gevel-frontaal.jpg', 'alt' => 'Voorgevel'],
            ['file' => 'kamer-1/CRW_7055.jpg', 'alt' => 'Kamer 1'],
            ['file' => 'kamer-3/CRW_6952.jpg', 'alt' => 'Terras'],
            ['file' => 'kamer-3/CRW_7089.jpg', 'alt' => 'Kamer 3'],
            ['file' => 'home/breakfast1.jpg', 'alt' => 'Ontbijt'],
        ],
        'leuven' => [
            ['file' => 'leuven/Leuven001.jpg', 'alt' => 'Leuven 1'],
            ['file' => 'leuven/Leuven018.jpg', 'alt' => 'Leuven 2'],
            ['file' => 'leuven/Leuven004.jpg', 'alt' => 'Leuven 3'],
            ['file' => 'leuven/Leuven002.jpg', 'alt' => 'Leuven 4'],
            ['file' => 'leuven/Leuven014.jpg', 'alt' => 'Leuven 5'],
            ['file' => 'leuven/Leuven010.jpg', 'alt' => 'Leuven 6'],
            ['file' => 'leuven/Leuven008.jpg', 'alt' => 'Leuven 7'],
            ['file' => 'leuven/Leuven006.jpg', 'alt' => 'Leuven 8'],
            ['file' => 'leuven/Leuven032.jpg', 'alt' => 'Leuven 9'],
            ['file' => 'leuven/Leuven024.jpg', 'alt' => 'Leuven 10'],
            ['file' => 'leuven/Leuven026.jpg', 'alt' => 'Leuven 11'],
            ['file' => 'leuven/Leuven028.jpg', 'alt' => 'Leuven 12'],
            ['file' => 'leuven/Leuven030.jpg', 'alt' => 'Leuven 13'],
            ['file' => 'leuven/Leuven020.jpg', 'alt' => 'Leuven 14'],
            ['file' => 'leuven/Leuven022.jpg', 'alt' => 'Leuven 15'],
        ],
        'kamer-1' => [
            ['file' => 'kamer-1/CRW_7055.jpg', 'alt' => 'Kamer 1'],
            ['file' => 'kamer-1/bgstr-shower.jpg', 'alt' => 'Douche'],
            ['file' => 'kamer-1/bgstr-dakterras.jpg', 'alt' => 'Dakterras'],
        ],
        'kamer-2' => [
            ['file' => 'kamer-2/bgstr-room2.jpg', 'alt' => 'Kamer 2'],
            ['file' => 'kamer-2/bgstr-room2-2.jpg', 'alt' => 'Kamer 2 detail'],
            ['file' => 'kamer-2/bgstr-room2door.jpg', 'alt' => 'Kamerdeur'],
        ],
        'kamer-3' => [
            ['file' => 'kamer-3/CRW_7089.jpg', 'alt' => 'Kamer 3'],
            ['file' => 'kamer-3/bgstr-bathroom.jpg', 'alt' => 'Badkamer'],
            ['file' => 'kamer-3/CRW_6952.jpg', 'alt' => 'Terras'],
        ],
    ],
    'rooms' => [
        'kamer-1' => [
            'title' => 'room 1',
            'nav_title' => '',
            'summary' => 'Rustige kamer met balkon, dakterras en een badkamer met zicht.',
            'image' => 'kamer-1/CRW_7055.jpg',
            'gallery' => 'kamer-1',
            'booking_url' => 'https://reservations.cubilis.eu/lodging-at-8-leuven?Language=nl-NL&Room=22492',
            'features' => [
                'Badkamer met zicht',
                'Douche en wc',
                'Koelkast',
                'Dubbel bed 1m80 breed',
                'Ergonomische hoofdkussens',
                'Kabeltelevisie en dvd-speler',
                'Internet',
                'Bedlinnen en handdoeken',
                'Balkon en dakterras',
                'Volledig gerenoveerde kamer',
                'Hedendaagse inrichting',
            ],
            'prices_heading' => 'Prijs per nacht',
            'prices' => [
                '1 persoon' => '89 euro',
                '2 personen' => '99 euro',
            ],
            'extra_info' => [
                'Inclusief ontbijt, wi-fi en taksen.',
                'Privé autostaanplaatsen aan 5 euro/nacht. Fietsen worden beschikbaar gesteld aan 8 euro/dag.',
                'Gratis vervoer van en naar station/luchthaven op eenvoudige vraag, vanaf een verblijf van 2 nachten en niet tussen 7:00 en 10:00u A.M.',
            ],
            'translations' => [
                'en' => [
                    'booking_url' => 'https://reservations.cubilis.eu/lodging-at-8-leuven?Language=en-GB&Room=22492',
                    'prices_heading' => 'Price per night',
                    'extra_info' => [
                        'Breakfast, Wi-Fi and taxes included.',
                        'Private parking spaces at 5 euro/night. Bikes are available at 8 euro/day.',
                        'Free transfer from and to the station/airport on request, from a stay of 2 nights and not between 7:00 and 10:00 A.M.',
                    ],
                ],
                'fr' => [
                    'booking_url' => 'https://reservations.cubilis.eu/lodging-at-8-leuven?Language=fr-FR&Room=22492',
                    'prices_heading' => 'Prix par nuit',
                    'extra_info' => [
                        'Petit déjeuner, Wi-Fi et taxes inclus.',
                        'Places de parking privées à 5 euros/nuit. Des vélos sont disponibles à 8 euros/jour.',
                        'Transport gratuit depuis et vers la gare/l’aéroport sur simple demande, à partir de 2 nuits et pas entre 7h00 et 10h00.',
                    ],
                ],
            ],
        ],
        'kamer-2' => [
            'title' => 'room 2',
            'nav_title' => '',
            'summary' => 'Ruime kamer met dakterras en alle hedendaags comfort.',
            'image' => 'kamer-2/bgstr-room2.jpg',
            'gallery' => 'kamer-2',
            'booking_url' => 'https://reservations.cubilis.eu/lodging-at-8-leuven?Language=nl-NL&Room=23747',
            'features' => [
                'Badkamer met zicht',
                'Douche en wc',
                'Koelkast',
                'Dubbel bed 1m80 breed',
                'Ergonomische hoofdkussens',
                'Kabeltelevisie en dvd-speler',
                'Internet',
                'Bedlinnen en handdoeken',
                'Dakterras',
                'Volledig gerenoveerde kamer',
                'Hedendaagse inrichting',
            ],
            'prices_heading' => 'Prijs per nacht',
            'prices' => [
                '1 persoon' => '89 euro',
                '2 personen' => '99 euro',
            ],
            'extra_info' => [
                'Inclusief ontbijt, wi-fi en taksen.',
                'Privé autostaanplaatsen aan 5 euro/nacht. Fietsen worden beschikbaar gesteld aan 8 euro/dag.',
                'Gratis vervoer van en naar station/luchthaven op eenvoudige vraag, vanaf een verblijf van 2 nachten en niet tussen 7:00 en 10:00u A.M.',
            ],
            'translations' => [
                'en' => [
                    'booking_url' => 'https://reservations.cubilis.eu/lodging-at-8-leuven?Language=en-GB&Room=23747',
                    'prices_heading' => 'Price per night',
                    'extra_info' => [
                        'Breakfast, Wi-Fi and taxes included.',
                        'Private parking spaces at 5 euro/night. Bikes are available at 8 euro/day.',
                        'Free transfer from and to the station/airport on request, from a stay of 2 nights and not between 7:00 and 10:00 A.M.',
                    ],
                ],
                'fr' => [
                    'booking_url' => 'https://reservations.cubilis.eu/lodging-at-8-leuven?Language=fr-FR&Room=23747',
                    'prices_heading' => 'Prix par nuit',
                    'extra_info' => [
                        'Petit déjeuner, Wi-Fi et taxes inclus.',
                        'Places de parking privées à 5 euros/nuit. Des vélos sont disponibles à 8 euros/jour.',
                        'Transport gratuit depuis et vers la gare/l’aéroport sur simple demande, à partir de 2 nuits et pas entre 7h00 et 10h00.',
                    ],
                ],
            ],
        ],
        'kamer-3' => [
            'title' => 'room 3',
            'nav_title' => '',
            'summary' => 'Gerenoveerde kamer met ingericht keukentje en extra eenpersoonsbed.',
            'image' => 'kamer-3/CRW_7089.jpg',
            'gallery' => 'kamer-3',
            'booking_url' => 'https://reservations.cubilis.eu/lodging-at-8-leuven?Language=nl-NL&Room=22493',
            'features' => [
                'Ingericht keukentje',
                'Douche en wc',
                'Koelkast',
                'Dubbel bed 1m80 breed',
                'Extra 1-persoonsbed',
                'Ergonomische hoofdkussens',
                'Kabeltelevisie en dvd-speler',
                'Internet',
                'Bedlinnen en handdoeken',
                'Volledig gerenoveerde kamer',
                'Hedendaagse inrichting',
                'Dakterras',
            ],
            'prices_heading' => 'Prijs per nacht',
            'prices' => [
                '1 persoon' => '104 euro',
                '2 personen' => '112 euro',
                '3 personen' => '139 euro',
            ],
            'extra_info' => [
                'Inclusief ontbijt, wi-fi en taksen.',
                'Privé autostaanplaatsen aan 5 euro/nacht. Fietsen worden beschikbaar gesteld aan 8 euro/dag.',
                'Gratis vervoer van en naar station/luchthaven op eenvoudige vraag, vanaf een verblijf van 2 nachten en niet tussen 7:00 en 10:00u A.M.',
            ],
            'translations' => [
                'en' => [
                    'booking_url' => 'https://reservations.cubilis.eu/lodging-at-8-leuven?Language=en-GB&Room=22493',
                    'prices_heading' => 'Price per night',
                    'extra_info' => [
                        'Breakfast, Wi-Fi and taxes included.',
                        'Private parking spaces at 5 euro/night. Bikes are available at 8 euro/day.',
                        'Free transfer from and to the station/airport on request, from a stay of 2 nights and not between 7:00 and 10:00 A.M.',
                    ],
                ],
                'fr' => [
                    'booking_url' => 'https://reservations.cubilis.eu/lodging-at-8-leuven?Language=fr-FR&Room=22493',
                    'prices_heading' => 'Prix par nuit',
                    'extra_info' => [
                        'Petit déjeuner, Wi-Fi et taxes inclus.',
                        'Places de parking privées à 5 euros/nuit. Des vélos sont disponibles à 8 euros/jour.',
                        'Transport gratuit depuis et vers la gare/l’aéroport sur simple demande, à partir de 2 nuits et pas entre 7h00 et 10h00.',
                    ],
                ],
            ],
        ],
    ],
    'pages' => [
        'home' => [
            'title' => 'Lodging at 8',
            'type' => 'home',
            'gallery' => 'home',
            'intro' => [
                'Logeren in de universiteitsstad Leuven, in een karaktervol huis uit 1918.',
                'Lodging at 8 ligt in de St-Kwintenswijk op een boogscheut van het centrum van de stad.',
                'Je verblijft in een rustige omgeving, in een huiselijke sfeer, met alle hedendaags comfort.',
                'De kamers zijn ruim en werden volledig gerenoveerd, waarbij oud en nieuw werden verzoend.',
                'In de zomermaanden kan je gebruik maken van een dakterras of de kleine tuin.',
                'In de onmiddellijke omgeving vind je ruime sportmogelijkheden en kan je genieten van de rust en het groen in enkele parken.',
                'De bekende Abdij van het Park ligt vlakbij.',
            ],
        ],
        'leuven' => [
            'title' => 'Leuven',
            'type' => 'leuven',
            'gallery' => 'leuven',
            'intro' => [
                'Louvain (Leuven) has a lot to offer: theatre, dance, expositions, music and film - for instance in the Contemporary Arts Centre STUK, the beguinage, beautiful churches, the old market square with its many pubs, cafes and restaurants, and of course the main square with its famous high gothic town hall.',
                'A city stuffed with universities is your best guarantee for a lively nightlife ...',
                'The local cuisine is renowned, with restaurants ranging from low-budget to more exquisite and sophisticated.',
                'Mingle with the locals and find out for yourself!',
                'Visit the official website of Louvain to learn more about city walks, guided tours, must-sees and the cultural agenda.',
                'Louvain, probably Belgium\'s best kept secret for an unforgettable time!',
            ],
        ],
        'locatie' => [
            'title' => 'Locatie',
            'type' => 'location',
            'map_url' => 'https://www.google.com/maps?q=Weldadigheidsstraat%208%2C%203000%20Leuven&output=embed',
            'intro' => [
                'Lodging at 8 ligt in de St-Kwintenswijk op een boogscheut van het centrum van de stad.',
                'De B&B is gemakkelijk te bereiken, zowel met de wagen als met het openbaar vervoer.',
                'Parkeren is mogelijk in de buurt. Het station, STUK, Sportoase en Abdij van Park liggen vlot bereikbaar.',
            ],
        ],
        'contact' => [
            'title' => 'contact',
            'type' => 'contact',
        ],
        'links' => [
            'title' => 'links',
            'type' => 'links',
            'columns' => [
                'In Leuven' => [
                    ['Leuven', 'https://www.leuven.be/'],
                    ['Katholieke Universiteit Leuven', 'https://www.kuleuven.be/'],
                    ['STUK kunstencentrum', 'https://www.stuk.be/'],
                    ['Museum M', 'https://www.mleuven.be/'],
                    ['Sportoase Leuven', 'https://www.sportoase.be/'],
                    ['Abdij van Park', 'https://www.parkabdij.be/'],
                    ['Belgische spoorwegen', 'https://www.belgiantrain.be/'],
                ],
                'Logeren en reizen' => [
                    ['Tripadvisor', 'https://www.tripadvisor.com/Hotel_Review-g188669-d1166577-Reviews-Lodging_at_8-Leuven_Flemish_Brabant.html'],
                    ['Bed and Breakfast Belgium', 'https://www.bedandbreakfast.be/'],
                    ['Taxi Gerard', 'https://www.taxigerard.be/'],
                ],
            ],
        ],
        'nieuws' => [
            'title' => 'Nieuws',
            'type' => 'news',
            'items' => [
                ['date' => '16/02', 'title' => 'Gemiddelde score op Booking.com 9/10 op basis van 315 reviews', 'body' => 'Een compacte nieuwslijst zoals op de originele website, klaar om verder aan te vullen.'],
                ['date' => '03/09', 'title' => 'Lodging at 8 krijgt podiumplaats op Tripadvisor', 'body' => 'Dank aan alle gasten van de voorbije jaren.'],
                ['date' => '30/01', 'title' => 'Leuven op de fiets', 'body' => 'Leuven bezoeken op de fiets kan makkelijk vanuit de B&B.'],
                ['date' => '10/12', 'title' => 'Vlakbij de B&B', 'body' => 'Abdij van Park ligt vlakbij en is een mooi decor voor wandelingen.'],
            ],
        ],
        'voorwaarden' => [
            'title' => 'algemene voorwaarden',
            'type' => 'terms',
            'intro' => [
                'Deze pagina is voorzien voor de algemene voorwaarden van Lodging at 8.',
                'Boekingen, annuleringen, betalingen en verblijf ter plaatse kunnen hier verder worden uitgewerkt volgens de actuele juridische tekst.',
            ],
        ],
    ],
];
