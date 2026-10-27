// Barra de acessibilidade: tamanho da fonte, alto contraste e VLibras (Libras)
(function () {
    var chave = "bib_acess";
    var estado = { tamanho: 100, contraste: false };

    try { estado = Object.assign(estado, JSON.parse(localStorage.getItem(chave) || "{}")); } catch (e) {}

    function salvar() { try { localStorage.setItem(chave, JSON.stringify(estado)); } catch (e) {} }

    function aplicar() {
        document.documentElement.style.fontSize = estado.tamanho + "%";
        document.body.classList.toggle("alto-contraste", !!estado.contraste);
        var bc = document.getElementById("btn-contraste");
        if (bc) bc.setAttribute("aria-pressed", estado.contraste ? "true" : "false");
    }

    function criarBotao(id, texto, rotulo, acao) {
        var b = document.createElement("button");
        b.type = "button"; b.id = id; b.textContent = texto; b.setAttribute("aria-label", rotulo);
        b.addEventListener("click", function () { acao(); salvar(); aplicar(); });
        return b;
    }

    var barra = document.createElement("div");
    barra.className = "barra-acessibilidade";
    barra.setAttribute("role", "region");
    barra.setAttribute("aria-label", "Recursos de acessibilidade");

    var rotulo = document.createElement("span");
    rotulo.textContent = "Acessibilidade:";
    barra.appendChild(rotulo);
    barra.appendChild(criarBotao("btn-menos", "A−", "Diminuir tamanho do texto", function () { estado.tamanho = Math.max(80, estado.tamanho - 10); }));
    barra.appendChild(criarBotao("btn-normal", "A", "Tamanho normal do texto", function () { estado.tamanho = 100; }));
    barra.appendChild(criarBotao("btn-mais", "A+", "Aumentar tamanho do texto", function () { estado.tamanho = Math.min(160, estado.tamanho + 10); }));
    var bc = criarBotao("btn-contraste", "Alto contraste", "Ativar ou desativar alto contraste", function () { estado.contraste = !estado.contraste; });
    bc.setAttribute("aria-pressed", "false");
    barra.appendChild(bc);

    document.body.insertBefore(barra, document.body.firstChild);
    aplicar();

    // VLibras (tradutor para Libras do governo; precisa de internet)
    var vl = document.createElement("div");
    vl.setAttribute("vw", ""); vl.className = "enabled";
    vl.innerHTML = '<div vw-access-button class="active"></div><div vw-plugin-wrapper><div class="vw-plugin-top-wrapper"></div></div>';
    document.body.appendChild(vl);

    var s = document.createElement("script");
    s.src = "https://vlibras.gov.br/app/vlibras-plugin.js";
    s.onload = function () { try { new window.VLibras.Widget("https://vlibras.gov.br/app"); } catch (e) {} };
    document.body.appendChild(s);
})();
