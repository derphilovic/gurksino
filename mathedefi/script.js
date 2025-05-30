var punkte;
var anzalRunden;
var rundenZähler;
const aryFragen = [];

function starteQuiz(){
    punkte = 0;
    anzalRunden = 3;
    rundenZähler = 0;

    erzeugefragen();
    starteRunde();
}

function erzeugefragen(){
arryFragen(0) ="Wie berechnist du das Kapital?# Z:P% Rechnit# P%*Z Rechnit# K:Z Rechnist# z*P% Rechnist";
arryFragen(1) ="Wie berechnist du die Zinsen?# Z:P% Rechnit# P%*Z Rechnit# K:Z Rechnist# z*P% Rechnist";
arryFragen(2) ="Wie berechnist du den Prozentsatz?# Z:P% Rechnit# P%*Z Rechnit# K:Z Rechnist# z*P% Rechnist";
}

function starteRunde(
    varaktuelleFrage = arryFragen.shift();
    const arryFragenAufbereitet = aktueleFrage.split("#");

    document.getElementById("idFrage").innerHTML = arryFragenAufbereitet[0];
){ 

}