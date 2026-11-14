# Encantados — História Co-op, Luar, Oficina de Criaturas e correção da arena 2v2

Substitua **api.php** e **index.html** (os mesmos arquivos da entrega anterior, agora atualizados). O banco migra sozinho para a versão 14. O guia de instalação anterior continua valendo: não envie o `game.db` antigo.

## 4) Arena 2v2: 2 princesas + 2 reis lado a lado
Arena 480×680 (1 unidade 3D = 20 px). Por equipe: 2 torres Princesa nas pistas e 2 torres Rei coladas no centro da base. Config em `AR4.tw` (`[x, y, rei?]`):

| Torre | Equipe azul (baixo) | Equipe vermelha (cima) | Vida | Ataque / alcance |
|---|---|---|---|---|
| Princesa esq. | (120, 590) | (120, 90) | 2200 | 55 / 125 |
| Princesa dir. | (360, 590) | (360, 90) | 2200 | 55 / 125 |
| Rei esq. | (208, 640) | (208, 40) | 3600 | 70 / 135 |
| Rei dir. | (272, 640) | (272, 40) | 3600 | 70 / 135 |

- A torre única central antiga (a de 2 torres sem rei da versão anterior) saiu. Os reis ficam a 64 px um do outro.
- **Vitória:** derrubar os **dois reis** inimigos. No tempo, vence quem derrubou mais torres e, se empatar, quem tem mais vida total. Se os dois lados caem no mesmo instante, é empate.
- **IA/unidades:** com a princesa da pista caída, a unidade vai para a torre viva mais próxima (normalmente um rei).
- O servidor passou a aceitar até 4 torres derrubadas por jogador (bônus de troféus 1,0 a 1,4×).

## 1) Modo História Co-op (dupla contra a IA)
Em cima do modo dupla que já existia (2 humanos × IA, mesma simulação nos dois aparelhos). Menu *Batalha → 📖 História (dupla)*.
- **8 capítulos** em sequência, cada um com chefão do folclore que entra aos 100 s restantes (Boi da Cara Preta gigante, Sucuriju, Lobisomem Alfa, Cuca, Minhocão, Boitatá, Mapinguari, Tupã), com vida e dano escalados. A dificuldade da IA vem do capítulo, definida **no servidor**.
- **Ervas compartilhadas:** um único poço de ervas para a dupla (enche com a mana gasta pelos dois e passivamente). 🌿 Arruda (−30% dano recebido), 🍃 Guiné (+30% dano), 🌱 Alecrim (+25% velocidade), 🍵 Boldo (cura 25%). Valem para todos os aliados em campo.
- **Combo de ervas:** ervas **diferentes** usadas por **jogadores diferentes** em até 6 s somam os efeitos com +50% de força por 12 s (6 combos nomeados; Arruda + Guiné também remove congelamento, pull e passos invertidos).
- **Elo de dupla 🔗:** se um jogador baixa uma criatura e o parceiro já tem em campo o par da lenda (Curupira + Caipora, Iara + Boto, Boitatá + Mapinguari, Tupã + Jaci…), as duas ganham +20% de vida e dano. Tabela em `LORE`. Funciona também no 2v2.
- **Doar mana 🎁:** 2 de mana para o parceiro.
- Tudo isso passa pelo mesmo canal sincronizado das cartas (`h:arruda` etc.), então os dois aparelhos aplicam no mesmo tick.
- **Progresso e recompensa:** `players.story`. Primeira vez: moedas e gemas por capítulo (150/3 até 800/10). Repetir paga 1/4 das moedas. O capítulo seguinte só abre depois do anterior (validado ao criar a sala). Só paga se a partida durou ≥ 45 s e teve ≥ 4 jogadas, para não dar para "relatar" vitória sem jogar.

## 2) Luar
- Fase calculada pelo ciclo sinódico (29,53 dias), mesma fórmula no JS e no PHP (`moonCode` / `moon_code`). Conferi com as luas reais de novembro de 2026.
- **Em partidas sincronizadas (PvP, dupla, 2v2, história) a fase vem do servidor**, calculada pela hora de criação da sala, para os aparelhos nunca divergirem (a simulação é lockstep). Em partidas solo usa a data local; `?lua=cheia|nova|crescente|minguante` na URL força a fase para testar.
- 🌕 **Cheia:** noturnas/aquáticas +15% dano, +10% velocidade, +10% resistência (Lobisomem, Mula, Iara, Boto, Boiúna, Rasga-Mortalha…). 🌑 **Nova:** furtivas da mata +20% velocidade, +15% resistência, +10% dano (Curupira, Caipora, Saci, Mapinguari…). Crescente/minguante: sem efeito. Listas em `MOON_N` / `MOON_F`, valores em `moonApply`.
- Cada capítulo da História pode fixar a fase (Cap. 1 e 7: Lua Nova; Cap. 3: Lua Cheia). A fase aparece no menu Batalha, no banner e no HUD.

## 3) Oficina de Criaturas
Menu *Batalha → 🛠️ Oficina*.
- **Decks:** lista dos seus decks, atalho para editar e análise do deck em uso (rápidas/médias/lentas, noturnas e furtivas, cartas com Elo). Os 5+ decks continuam salvos como antes.
- **Criar criatura:** parte de uma criatura sua e permite ±30% em vida, dano e velocidade, com nome (3–16 letras) e ícone de uma lista fixa. **Cada registro custa 5.000 moedas.**
- **Validação do saldo (servidor, `craft_card`):**
  1. todas as regras de conteúdo são checadas **antes** de cobrar (base é sua, nome, ícone, equilíbrio, limite de 12, nome repetido);
  2. a cobrança é uma única instrução atômica, `UPDATE players SET gold=gold-5000 WHERE id=? AND gold>=5000`, e se não alterar nenhuma linha a compra é negada com "faltam N moedas". Isso impede saldo negativo mesmo com dois cliques simultâneos;
  3. débito, criação da carta e inclusão em `owned` estão na **mesma transação**: qualquer erro desfaz tudo e nenhuma moeda é cobrada.
  O cliente só espelha a regra para mostrar o botão desativado e a confirmação.
- **Equilíbrio:** a criatura pode **trocar** atributos, nunca ficar mais forte que a base para o mesmo custo de mana (mesma métrica poder-por-mana do rebalanceamento). Ex.: Saci com +30% vida e −30% dano passa; +30% de vida sozinho é recusado.
- **Multiplayer:** as criaturas ficam na tabela `custom_cards`, entram no catálogo (`?a=cards`) e os clientes baixam sozinhos o que faltar quando o adversário jogar uma carta desconhecida. Elas herdam arte e modelo 3D da base, ficam fora da loja, da IA e das listas de arenas dos outros jogadores.

## Testado e não testado
Testado: sintaxe do JS completo, referências de ids (chefões, elos, listas da Lua), fase lunar contra luas reais, lógica de ervas/combo/doação/elo/Lua com stubs, balanço de chaves do PHP, e o SQL novo contra uma cópia do seu `game.db` (migrações, cobrança de 5.000 que nega com saldo insuficiente, ids sequenciais, regra de equilíbrio).
**Não testado:** não há PHP nem navegador neste ambiente. Faça uma partida real em dupla (História) e uma de 4 (2v2) antes de publicar. Confira principalmente o enquadramento dos dois reis na arena grande e o layout dos botões de ervas em telas pequenas.

## Pontos de atenção
- **Persistência:** as criaturas pagas vivem no banco. Se o Render apagar o `game.db`, elas somem junto com as moedas gastas, e o "cofre" do aparelho não inclui `custom_cards`. Configure o Disk (`DATA_DIR`) antes de lançar uma economia de 5.000 moedas.
- **Antifraude da História:** o resultado ainda é relatado pelos clientes, como já era no modo dupla; a checagem de tempo/jogadas só reduz o abuso. Quorum como no 2v2 seria o próximo passo.
- Os decks continuam só no `localStorage` do aparelho. Sincronizá-los no servidor evitaria perdê-los junto com o cache do navegador.
