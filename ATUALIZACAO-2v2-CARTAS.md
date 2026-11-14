# Encantados — Cartas novas, Velocidade e 2v2 com 4 jogadores

## Como instalar
Troque **api.php** e **index.html** (o `.htaccess` não muda). Não envie o `game.db` antigo: na primeira requisição o `api.php` migra o banco para a versão 13 (cria as tabelas `cards` e `match_players`, grava as 86 cartas e dá todas as cartas ao ADMIN X). Faça o backup do ADMIN X antes, como no LEIA-ME.

## 1) Cartas e Velocidade
- **27 cartas novas**: 22 criaturas, 3 feitiços e 2 construções (comuns 6, raras 9, épicas 8, lendárias 3, mítica 1). Desbloqueiam pelas arenas (campo `z`).
- **Velocidade** (`v`) em todas as 86 cartas: 0 Rápida (vel. ≥ 76), 1 Média (56–75), 2 Lenta (≤ 55). Em feitiços e construções ela significa *ritmo* (instantâneo / médio / prolongado; intervalo de ataque ou spawn). Aparece na carta (⚡ 🚶 🐢).
- **Rebalanceamento**: vida média por mana ficou Rápida ≈ 49, Média ≈ 76, Lenta ≈ 139. Saci 120→95 de vida e velocidade 88; Boitatá 600→800 de vida, lento (40); Cuca 300→540, lenta, dano menor. Cartas que eram rápidas **e** robustas foram ajustadas: Tupã 650→440, Onça 520→330, Labatut 460→330, Mula 300→235, entre outras. Conferi com uma métrica de poder por mana: todas as raridades ficaram em faixa estreita (comum 26–35, mítica 41–44).
- Tabela antes→depois de todas as criaturas: `balance_table.md`.
- Novas mecânicas: *Lago da Vitória-Régia* (zona de cura), *Pegadas do Curupira* (inimigos andam de costas por 3 s), *Pororoca* (dano + empurra para trás). A IA já sabe usá-las.
- **Banco**: tabela `cards` (id, custo, vida, dano, velocidade, classe de velocidade, alcance, ritmo, tipo, raridade, arena, extra). Endpoint `?a=cards`. O jogo carrega no boot e os números do banco substituem os do JS. Para ajustar balanceamento basta fazer `UPDATE cards SET hp=... WHERE id='saci'` e recarregar o jogo. Cartas totalmente novas ainda exigem arte no `index.html` (REC/SP3).

## 2) Arena ampliada e 2v2 real
- **Arena 2v2**: 480×680 (a clássica é 360×520), câmera, névoa, pontes, muros e árvores gerados por `buildArena()`. O 1x1 e o modo dupla continuam na arena clássica.
- **2 torres por equipe**, uma por pista, **3200 de vida cada** (as torres do 1x1 têm 900 e o rei 1500) e ataque 60 (antes 45) com alcance 125. Vence quem derrubar as 2 torres do rival; no tempo, vale número de torres e depois vida total. Partida de 180 s + 90 s de morte súbita. Os valores estão em `AR4` no `index.html`.
- **4 humanos**: nova rota no menu *Batalha → ⚔️⚔️ 2v2 · 4 jogadores*, com fila aleatória ou sala por código/link (`?sala=CODIGO&m=2v2`) e escolha de equipe. Cada jogador tem baralho e mana próprios.
- **Sincronização**: o mesmo modelo lockstep do 1x1, estendido para 4. O servidor guarda um tick por jogador (`match_players`) e só retransmite comandos. Cada cliente só avança enquanto estiver a menos de 45 ticks (1,5 s) do mais lento dos outros jogadores ativos. Endpoints: `q4_queue`, `q4_sync`, `q4_cancel`, `q4_end`.
- **Queda de jogador**: sem sinal por 20 s ele é dado como desconectado, os outros param de esperar por ele e a partida continua. Se uma equipe inteira cair, a outra vence. Quem caiu ou desistiu conta derrota.
- **Resultado**: cada jogador relata A/B/empate e o servidor fecha quando 2 relatos coincidem (ou após 10 s, pelo mais votado). Uma só pessoa não consegue forjar a vitória. A recompensa é paga uma vez por jogador: vitória +30🏆 (bônus por torre derrubada), derrota −12🏆, e conta pontos no Torneio de Clãs.

## O que foi testado, e o que NÃO foi
Testado aqui: sintaxe do JavaScript inteiro (`node --check`), cobertura de arte 2D/3D das 86 cartas, balanço de chaves do PHP, e todo o SQL novo contra uma cópia do seu `game.db` (migração, entrada de 4 jogadores nas vagas certas, comando bloqueado para jogador caído, pagamento único, votação). A simulação `teste_lockstep_4_jogadores.js` roda 4 clientes com latência de 40–300 ms e uma queda: todos terminam com o mesmo estado e ninguém trava.
**Não testado**: este ambiente não tem PHP nem navegador. Rode uma partida real com 4 abas/aparelhos antes de publicar e confira nos logs do PHP se há erros. Pontos que merecem olho: enquadramento da câmera na arena grande (`AR4.cam`), e carga do servidor, já que são 4 requisições longas simultâneas por partida (se ficar pesado, aumente `G.syT` do 2v2).

## Observações
- O jogo desenha em **Three.js (WebGL)**, não em Canvas 2D; mantive essa base.
- Limitação conhecida do lockstep: aba em segundo plano pausa a partida e, após 20 s, o jogador é dado como caído. Não há reconexão no meio da partida.
- Já existia (não mexi): a senha padrão do ADMIN X (`@GL123`) e o `SAVE_SECRET_DEFAULT` estão escritos no `api.php`. Troque a senha e defina `SAVE_SECRET` no Render. Além disso, a fila aleatória do 1x1 não filtra salas com código, então um desconhecido pode cair numa sala privada de 1x1.
