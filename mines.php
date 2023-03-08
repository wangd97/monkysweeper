<!DOCTYPE html>
<html>
	<head>
		<title>Monkysweeper</title>
		<link rel="icon" type="image/x-icon" href="favicon.ico">
		<link rel="stylesheet" href="lib/style.css">
		
		<link rel="preconnect" href="https://fonts.googleapis.com">
		<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
		<link href="https://fonts.googleapis.com/css2?family=Nunito:wght@200;300;400;500&display=swap" rel="stylesheet">

		<script src="lib/jquery.js"></script>

		<script>let rngSeed = Math.floor(Math.random() * 1000000000);</script>
		<script src="lib/seedrandom.js"></script>
		<script>Math.seedrandom(rngSeed)</script>

	</head>

	<body>

		<div id="container">
			<div id="board"></div>
			<div id="info-container">
				<div id="timer"></div>
				<div id="flags"></div>
				<div id="safes"></div>
				<div id="deaths"></div>
			</div>
		</div>


		<script>








			const timer = {
				state: 'stopped', // stopped, running, paused
				startTime: undefined,
				pauseTime: undefined,
				elapsedTime: 0,
				interval: undefined,
				precision: 100, // ms
				render: function () {
					$('#timer').html(timer.format(timer.elapsedTime));
				},
				reset: function () {
					timer.state = 'stopped';
					timer.elapsedTime = 0;
					clearInterval(timer.interval);
					timer.render();
				},
				start: function () {
					timer.state = 'running';
					timer.startTime = Date.now();
					timer.interval = setInterval(timer.updateWhileNotGameOver, timer.precision);
				},
				pause: function () {
					if (timer.state === 'running') {
						timer.pauseTime = Date.now();
						timer.state = 'paused';
					}
				},
				resume: function () {
					if (timer.state === 'paused') {
						timer.startTime += Date.now() - timer.pauseTime;
						timer.state = 'running';
					}
				},
				stop: function () {
					timer.state = 'stopped';
					clearInterval(timer.interval);
					timer.update();
				},
				update: function () {
					if (timer.state === 'running') {
						timer.elapsedTime = Date.now() - timer.startTime;
						timer.render();
					}
				},
				updateWhileNotGameOver: function () {
					if (boardInfo.isGameOver) {
						timer.pause();
					}
					else {
						timer.resume();
					}
					timer.update();
				},
				format: function (time) {
					// Format the time in the format mm:ss
					const minutes = Math.floor(time / 60000);
					const seconds = Math.floor((time % 60000) / 1000);
					return `${minutes.toString()}:${seconds.toString().padStart(2, '0')}`;
				}
			}
			



			$("#board").on("contextmenu", function() {return false;});
			var audio = new Audio('audio/Roblox-death-sound.mp3');

			let boardInfo;
			let board;
			let allCells;
			let forceDeducible = true;
			let cellIntervals;

			// If no data is passed in, reset all default data
			// If data is passed in, it needs properties: boardInfo, board
			function createBoard (data = {}) {

				// Create boardInfo
				if (data.boardInfo) {
					boardInfo = data.boardInfo;
				}
				else {
					boardInfo = {
						rows: 16,
						cols: 30,
						totalMines: 170, // desired number of mines
						numMines: 0, // actual number of mines currently on the board
						numFlags: 0,
						numUnknownSafes: undefined,
						isGameStarted: false,
						isGameOver: false,
						won: false,
						lost: false,
						numDeaths: 0,
						// for making board deducible
						suppressWin: false,
						// lastNPerturbChoices: ['', '', ''],
						globalDeductionMineThreshold: 15,
						maxRecursionDepth: 15
					};
					boardInfo.numUnknownSafes = boardInfo.rows * boardInfo.cols - boardInfo.totalMines;
				}

				// Create board (2D array of every cell)
				if (data.board) {
					board = data.board;
				}
				else {
					board = [];
					for (let i = 0; i < boardInfo.rows; i++) {
						board[i] = [];
						for (let j = 0; j < boardInfo.cols; j++) {
							board[i][j] = {
								row: i,
								col: j,
								mine: false,

								// States: unknown is default, left click to reveal, right click to flag
								unknown: true,
								revealed: false,
								flagged: false,

								// Neighbor stuff
								neighbors: [],
								neighborMines: 0,
								neighborFlags: 0,

								// Cosmetic states
								overflagged: false,
								exploded: false,
								previewing: false,

								// False for first clicked cell & its neighbors
								canBeMine: true,

								// When rendering, only update cells marked true
								markedForRendering: false,

								// For making board deducible
								markedForDeduction: false
							};
						}
					}
				}

				// Init allCells array as a flattened (1D) version of board (2D)
				allCells = [].concat(...board);

				// Init 'neighbors' property of each cell with references to each neighboring cell
				allCells.forEach(c => {
					c.neighbors = allCells.filter(n => Math.abs(c.row - n.row) <= 1 && Math.abs(c.col - n.col) <= 1 && n !== c);
				})

				// // Create 2D array to hold intervals (from setInterval) for each cell
				// cellIntervals = Array(boardInfo.rows).fill().map(() => Array(boardInfo.cols).fill());
			}

			function flagCell (cell) {
				if (cell.unknown) {
					cell.revealed = false;
					cell.flagged = true;
					cell.unknown = false;

					// Update neighbors
					cell.neighbors.forEach(n => {
						n.neighborFlags++;
						n.overflagged = n.neighborFlags > n.neighborMines;
					});

					boardInfo.numFlags++;
					return true;
				}
				return false;
			}

			function unflagCell (cell) {
				if (cell.flagged) {
					cell.revealed = false;
					cell.flagged = false;
					cell.unknown = true;

					// Update neighbors
					cell.neighbors.forEach(n => {
						n.neighborFlags--;
						n.overflagged = n.neighborFlags > n.neighborMines;
					});

					boardInfo.numFlags--;
					return true;
				}
				return false;
			}

			function revealCell (cell) {
				if (cell.unknown) {
					cell.revealed = true;
					cell.flagged = false;
					cell.unknown = false;
					if (!cell.mine) boardInfo.numUnknownSafes--;
					return true;
				}
				return false;
			}

			function putMine (cell) {
				// only for generating deducible board
				if (cell.mine) return false;
				cell.mine = true;
				cell.neighbors.forEach(n => {
					n.neighborMines++;
					n.overflagged = n.neighborFlags > n.neighborMines;
				});
				boardInfo.numMines++;
				return true;
			}

			function removeMine (cell) {
				if (!cell.mine) return false;
				// only for generating deducible board
				cell.mine = false;
				cell.neighbors.forEach(n => {
					n.neighborMines--;
					n.overflagged = n.neighborFlags > n.neighborMines;
				});
				boardInfo.numMines--;
				return true;
			}

			function resetRngSeed (seed) {
				if (seed === undefined) seed = Math.floor(Math.random() * 1000000000);
				Math.seedrandom(seed);
				console.log('RNG Seed: ' + seed);
			}

			function startGame (clickedRow, clickedCol) {
				workTimes.total = Date.now();
				
				resetRngSeed(999258680);

				if (forceDeducible) {
					while (true) {
						if (initMineLocations(clickedRow, clickedCol)) break;
					}
				}
				else {
					initRandomMineLocations(clickedRow, clickedCol);
				}
				boardInfo.isGameStarted = true;
				timer.start();

				// Timing
				let time1 = Date.now();
				workTimes.render += Date.now() - time1;
				workTimes.total = Date.now() - workTimes.total;
				console.log(workTimes);
			}

			// Make the board deducible by simulating a playthrough, and making changes if necessary
			// Returns true if success, else returns false
			function initMineLocations (clickedRow, clickedCol) {

				do {
					createBoard();
					boardInfo.isGameStarted = true;
					boardInfo.suppressWin = true;
					initRandomMineLocations(clickedRow, clickedCol);
					moveUndeducibleMines();
				} while (existsUndeducibleHiddenRegion());

				// Repeatedly deduce and perturb mines
				handleLeftClick(clickedRow, clickedCol);
				while (deduce() || perturb());

				// If failed to generate deducible board, return false
				if (!boardInfo.won) {
					workTimes.retry++;
					return false;
				}

				// Remember mine locations of deducible board
				// Careful: mineCells contains discarded cells that are not part of the board variable
				const mineCells = allCells.filter(c => c.mine);

				// Reset board
				createBoard();

				const clickedCell = board[clickedRow][clickedCol];
				clickedCell.canBeMine = false;
				clickedCell.neighbors.forEach(n => n.canBeMine = false);

				// Place mines on the board
				// Careful: mineCells contains discarded cells that are not part of the board variable
				mineCells.forEach(c => putMine(board[c.row][c.col]));
				
				return true;
			}

			// Generate random mine locations
			// Avoid the clicked cell and its neighbors
			// Always returns true;
			function initRandomMineLocations (clickedRow, clickedCol) {
				const clickedCell = board[clickedRow][clickedCol];
				clickedCell.canBeMine = false;
				clickedCell.neighbors.forEach(n => n.canBeMine = false);

				// Mines can be placed on any cell except for the clicked cell and its neighbors
				const possibleMineCells = allCells.filter(c => c.canBeMine);

				// Shuffle possibleMineCells and pick the first N cells to have a mine
				shuffleArray(possibleMineCells).slice(0, boardInfo.totalMines).forEach(c => putMine(c));

				// // For adding custom mine locations
				// // 	rows: 4,
				// // 	cols: 5,
				// // 	numMines: 5,
				// allCells.forEach(c => removeMine(c));
				// putMine(board[0][0]);
				// putMine(board[1][1]);
				// putMine(board[1][2]);
				// putMine(board[1][3]);
				// putMine(board[0][4]);
			}

			// Shuffle an array in place
			// Returns the array (as an option to make syntax cleaner)
			function shuffleArray (array) {
				for (let i = array.length - 1; i > 0; i--) {
					const j = Math.floor(Math.random() * (i + 1));
					[array[i], array[j]] = [array[j], array[i]];
				}
				return array;
			}

			// Handle clicking on a cell
			// Returns true if a change was made, false otherwise
			function handleLeftClick (row, col) {

				// Game must not be over
				if (boardInfo.isGameOver) return false;

				// If the game has not started yet, generate the board
				if (!boardInfo.isGameStarted) {
					startGame(row, col);
				}

				// Cell is not defined until now, since board gets reset to new cells after deduction
				const cell = board[row][col];

				// If square is a revealed hint
				if (cell.revealed && cell.neighborMines > 0 && !cell.mine) {

					// If flags < mines, then preview the unknown neighbors
					if (cell.neighborFlags < cell.neighborMines) {
						previewNeighbors(cell);
					}

					// If flags == mines, then reveal unknown neighbors
					let madeChanges = false;
					if (cell.neighborFlags === cell.neighborMines) {
						cell.neighbors.forEach(n => {
							if (n.unknown && handleLeftClick(n.row, n.col)) madeChanges = true;
						});
					}
					return madeChanges;
				}

				// If square is flagged: do nothing
				if (cell.flagged) return false;

				// If square is unknown:
				if (cell.unknown) {

					// Mark cell to be rendered
					cell.markedForRendering = true;

					// Reveal the square
					revealCell(cell);

					// Check for game over
					if (cell.mine) {
						lose(cell);
						return true;
					}

					// Mark this square's neighbors for deduction
					cell.neighbors.forEach(n => n.markedForDeduction = true);

					// If the square has no neighboring mines, reveal its neighbors
					if (cell.neighborMines == 0) {
						cell.neighbors.forEach(n => {
							if (n.unknown) handleLeftClick(n.row, n.col);
						});
					}

					// Check for victory
					if (checkVictory()) {
						victory();
					}

					return true;
				}

				return false;
			}

			// Handle right-clicking to flag a square
			// Returns true if a change was made, false otherwise
			function handleRightClick (row, col) {
				const cell = board[row][col];

				// Game must be started
				if (!boardInfo.isGameStarted) return false;

				// Cannot flag a revealed square
				if (cell.revealed) return false;

				// Toggle the flagged status of the square
				if (!cell.flagged) flagCell(cell);
				else if (cell.flagged) unflagCell(cell);

				// Mark this cell and its neighbors to be rendered
				cell.markedForRendering = true;
				cell.neighbors.forEach(n => n.markedForRendering = true);

				// Mark this square and its neighbors for deduction
				cell.markedForDeduction = true
				cell.neighbors.forEach(n => n.markedForDeduction = true);

				return true;
			}

			// Handle lose
			function lose (cell) {
				
				boardInfo.isGameOver = true;
				boardInfo.lost = true;
				boardInfo.numDeaths++;
				
				revealCell(cell);
				cell.exploded = true;

				// Game over message
        		audio.play();
				console.log('Game over!');
			}

			// Check if all non-mine squares are revealed
			// Can also add an option to check if enough flags have been placed down
			function checkVictory () {
				return allCells.filter(c => c.revealed).length === boardInfo.rows * boardInfo.cols - boardInfo.numMines;
			}

			// Handle victory
			function victory () {

				// Show all the mines as flags
				for (const cell of allCells) {
					if (cell.mine && !cell.flagged) handleRightClick(cell.row, cell.col);
				}

				// Update board info
				boardInfo.isGameOver = true;
				boardInfo.won = true;

				// Display victory
				if (boardInfo.suppressWin) return;

				console.log('Congratulations, you won!');
				// TODO: uncomment the line below when Dylan FINALLY fixes his stuff smh
        		window.open('https://www.youtube.com/watch?v=ymdhRMiMGK0', 'popup', config='height=375,width=450')
			}

			function renderBoard (renderWholeBoard = false) {
				let time1 = Date.now();
				if (!boardInfo.isGameStarted) {
					renderBoardBeforeGameStarted();
				}
				else {
					renderBoardAfterGameStarted(renderWholeBoard);
				}

				// Display how many flags placed
				$('#flags').html('Mines: ' + boardInfo.numFlags + '/' + boardInfo.totalMines);

				// Near the end of the game, display how many safe squares remaining
				boardInfo.numUnknownSafes < 10 ? $('#safes').html('Safe: ' + boardInfo.numUnknownSafes) : $('#safes').empty();

				// Display number of deaths
				boardInfo.numDeaths > 0 ? $('#deaths').html('Deaths: ' + boardInfo.numDeaths) : $('#deaths').empty();
			}

			// Create a fully unknown board with event listeners
			function renderBoardBeforeGameStarted () {

				// Draw table
				$board = $("#board");
				$board.empty();
				for (let i = 0; i < boardInfo.rows; i++) {
					for (let j = 0; j < boardInfo.cols; j++) {
						let $cell = $($('<div class="square unknown" data-row="' + i + '" data-col="' + j + '"></div>'));
						$board.append($cell);
					}
				}

				$("#board").css('grid-template-rows', 'repeat(' + boardInfo.rows + ', 1fr)');
				$("#board").css('grid-template-columns', 'repeat(' + boardInfo.cols + ', 1fr)');

				// Set up event listeners for each square
				$('.square').each((index, square) => {
					const LEFT_CLICK = 1;
					const RIGHT_CLICK = 3;

					const row = parseInt($(square).attr('data-row'));
					const col = parseInt($(square).attr('data-col'));

					$(square).mousedown((e) => {
						if (e.which == LEFT_CLICK) {
							if (handleLeftClick(row, col)) {
								savestates.save();
								renderBoard();
							}
						}
						if (e.which == RIGHT_CLICK) {
							if (handleRightClick(row, col)) {
								savestates.save();
								renderBoard();
							}
						}
					});
				});
			}

			// Render board HTML
			function renderBoardAfterGameStarted (renderWholeBoard = false) {
				const cellsToRender = renderWholeBoard ? allCells : allCells.filter(c => c.markedForRendering);

				for (const cell of cellsToRender) {
					
					// Find the div element (square) of this row and col
					const $cell = $($('[data-row=' + cell.row + '][data-col=' + cell.col + ']'));

					// Reset classes
					$cell.attr('class', 'square');

					// Render visual state of this square
					if (cell.exploded)
						$cell.addClass('mine').addClass('exploded');
					if (cell.revealed) {
						$cell.addClass('revealed');
						if (cell.overflagged)
							$cell.addClass('overflagged');
					}
					if (cell.unknown) {
						$cell.addClass('unknown');
						if (cell.previewing)
							$cell.addClass('previewing');
					}
					if (cell.flagged)
							$cell.addClass('flagged');

					// Render hint number of this square
					if (cell.revealed && !cell.mine && cell.neighborMines > 0) {
						$cell.html(cell.neighborMines);
						$cell.css('color', 'var(--color' + cell.neighborMines + ')');
					}
					else {
						$cell.empty();
					}

					// Unmark cell for rendering
					cell.markedForRendering = false;
				}
				
				// If lost, render siren effect
				if (boardInfo.lost) {
					$('.square').addClass('flash');
					const timeBetweenFlashes = 150; // ms
					const numFlashes = 2;

					const flashStyle = $('<style id="flash-style">.flash {background-color: red !important}</style>');

					for (let i = 0; i < numFlashes - 1; i++) flashOnce();
					setTimeout(flashOnce, timeBetweenFlashes * 2);

					function flashOnce () {
						$('body').append(flashStyle);
						setTimeout(function () {
							flashStyle.remove();
						}, timeBetweenFlashes);
					}
				}
			}






			// Undos

			$(document).keydown(function(e) {
				// Q: Deduce
				if (e.which === 81) {
					deduce();
					savestates.save();
					renderBoard(true);
				}
				// W: Perturb
				if (e.which === 87) {
					perturb();
					savestates.save();
					renderBoard(true);
				}
				// R: Reset
				if (e.which === 82) {
					createBoard();
					savestates.save();
					renderBoard(true);
				}
				// Ctrl + Z
				if (e.which === 90 && e.ctrlKey) {
					savestates.undo();
					renderBoard(true);
				}
				// Ctrl + Y
				if (e.which === 89 && e.ctrlKey) {
					savestates.redo();
					renderBoard(true);
				}
			});

			// Savestates
			let savestates = {
				states: [],
				index: -1,
				save: function() {
					this.index++;
					this.states[this.index] = {
						boardInfo: structuredClone(boardInfo),
						board: structuredClone(board)
					};
					this.states.length = this.index + 1;
				},
				undo: function() {
					if (this.index <= 0) return;
					this.index--;
					const oldNumDeaths = boardInfo.numDeaths;
					createBoard({
						boardInfo: structuredClone(this.states[this.index].boardInfo),
						board: structuredClone(this.states[this.index].board)
					});
					boardInfo.numDeaths = oldNumDeaths;
				},
				redo: function() {
					if (this.index + 1 >= this.states.length) return;
					this.index++;
					createBoard({
						boardInfo: structuredClone(this.states[this.index].boardInfo),
						board: structuredClone(this.states[this.index].board)
					});
				}
			};



			let workTimes = {
				total: 0,
				remine: 0,
				numRemines: 0,
				render: 0,
				hiddenRegions: 0,
				retry: 0,
				doEasyDeduction: 0,
				doAutoComplete: 0,
				local: 0,
				localsuccess: 0,
				global: 0,
				globalsuccess: 0,
				clustering: 0,
				helper: 0,
				perturb: 0,
				clusters: 0,
				maxDepth: 0
			}
			let time = 0;

			let hintCells = [];

			function deduce () {

				// Game must be active
				if (!boardInfo.isGameStarted) return false;
				if (boardInfo.isGameOver) return false;
				
				// hintCells: every cell with a number, that is not completed with flags
				hintCells = allCells.filter(c => c.revealed && c.neighborFlags < c.neighborMines);

				// Deduce flags
				let time = Date.now();
				if (doEasyDeduction()) {
					workTimes.doEasyDeduction += Date.now() - time;
					return true;
				}
				workTimes.doEasyDeduction += Date.now() - time;
				time = Date.now();
				if (doAutoComplete()) {
					workTimes.doAutoComplete += Date.now() - time;
					return true;
				}
				workTimes.doAutoComplete += Date.now() - time;
				time = Date.now();
				if (doLocalDeduction()) {
					workTimes.localsuccess++;
					workTimes.local += Date.now() - time;
					return true;
				}
				workTimes.local += Date.now() - time;
				time = Date.now();
				if (boardInfo.numMines - boardInfo.numFlags <= boardInfo.globalDeductionMineThreshold && doGlobalDeduction()) {
					workTimes.globalsuccess++;
					workTimes.global += Date.now() - time;
					return true;
				}
				workTimes.global += Date.now() - time;
				// Fail to make a change
				return false;
			}

			function perturb () {
				// savestates.save();
				// console.log('w');

				let thisTime = Date.now();

				// Game must be active
				if (!boardInfo.isGameStarted) return false;
				if (boardInfo.isGameOver) return false;
				
				// hintCells: every cell with a number, that is not completed with flags
				hintCells = allCells.filter(c => c.revealed && c.neighborFlags < c.neighborMines);

				let madeChanges = false;

				// Perturb mines
				if (hintCells.length > 0 && randomlyFillOrClearHint()) {
					workTimes.perturb += Date.now() - thisTime;
					return true;
				}

				// Move undeducible mine
				if (moveUndeducibleMines()) {
					workTimes.perturb += Date.now() - thisTime;
					return true;
				}

				// Fail to make a change
				workTimes.perturb += Date.now() - thisTime;

				return false;
			}

			// Does easy flags + easy opens + cleanup
			// Returns true if changes are made, else returns false

			
			function doEasyDeduction () {
				let madeChanges = false;
				if (doEasyFlags()) madeChanges = true;
				if (doEasyOpens()) madeChanges = true;
				if (doCleanUp()) madeChanges = true;
				return madeChanges;
			}

			// A cluster is a local group of hints and unknown cells
			// Starting with 1 hint, all other hints that share an unknown neighbor cell are also part of the cluster
			// The unknown cells of the cluster contain all the unknown neighbors of all the hints in the cluster
			// This function deduces the flags and safe cells for all clusters
			// This is relatively fast; use this when easy deduction fails
			function doLocalDeduction () {

				// Find clusters
				const clusters = findClustersToDeduce();

				// Will deduce all clusters; unmark all for deduction
				allCells.forEach(c => c.markedForDeduction = false);

				return deduceClusters(clusters);
			}

			// This function deduces the flags and safe cells for the cluster that contains the entire board's hints, and their neighboring unknown cells
			// This is slow; use this near the end of the game when there are few mines remaining (threshold set in boardInfo)
			function doGlobalDeduction () {
				
				// Will deduce all clusters; unmark all for deduction
				allCells.forEach(c => c.markedForDeduction = false);

				// All incomplete hints and all neighboring unknown cells go in the same cluster
				const unknownCells = [];
				hintCells.forEach(c => unknownCells.push(...c.neighbors.filter(n => n.unknown)));
				const cluster = {
					hintCells: hintCells,
					unknownCells: [...new Set(unknownCells)],
					alwaysReachesFlagLimit: false
				}

				// Make deductions
				let madeChanges = deduceClusters([cluster]);

				// If flag limit is always reached, then every unknown cell, that does not neighbor the hints, can be opened
				if (cluster.alwaysReachesFlagLimit) {
					const hiddenCells = allCells.filter(c => c.unknown && !unknownCells.includes(c));
					hiddenCells.forEach(c => {
						if (c.unknown && handleLeftClick(c.row, c.col)) madeChanges = true;
					});
				}

				return madeChanges;
			}

			// Try every way to fill up each cluster with flags
			// If a cell is unflagged in every configuration of that cluster, then we can open it
			// If a cell is flagged in every configuration of that cluster, then we can flag it
			// Attaches a key 'alwaysReachesFlagLimit' (true/false) to each cluster,
			// which is true if solving this cluster always uses the max flags, false otherwise
			function deduceClusters (clusters) {
				let madeChanges = false;
				const numFlags = [];

				// For each cluster: find every combination of flags that satisfies all hints in the cluster
				for (const cluster of clusters) {

					const results = {
						alwaysSafeCells: [...cluster.unknownCells],
						alwaysFlaggedCells: [...cluster.unknownCells],
						numFlags: []
					}

					// Try every legal combination of flags
					deduceOneCluster(cluster, results);

					// If no solutions were generated, this cluster fails
					if (results.numFlags.length === 0) continue;

					// Check if the flag limit is always reached
					if (results.numFlags.length > 0 && results.numFlags.every(num => num === boardInfo.numMines)) cluster.alwaysReachesFlagLimit = true;

					// If a cell is always safe, open it
					results.alwaysSafeCells.forEach(c => {
						if (c.unknown && handleLeftClick(c.row, c.col)) madeChanges = true;
					});

					// If a cell is always flagged, flag it
					results.alwaysFlaggedCells.forEach(c => {
						if (handleRightClick(c.row, c.col)) madeChanges = true;
					});

					// End this function as soon as any changes are made, to save time
					if (madeChanges) break;
				}

				return madeChanges;
			}

			// Recursively makes every valid flag guess for a cluster
			// The results object should be generated beforehand and passed in, and contains the keys:
			// 		alwaysSafeCells: array of neighboring unknown cells; retains cells that are safe in all flag configurations
			//		alwaysFlaggedCells: array of neighboring unknown cells; retains cells that are flagged in all flag configurations
			//		numFlags: empty array; filled with the number of flags used in each successful configuration
			// If the recursion limit is reached: the function fails, and all values in the results will be an empty array
			function deduceOneCluster (cluster, results, depth = 1) {

				if (depth > workTimes.maxDepth) workTimes.maxDepth = depth;

				// Base case: recursion depth limit reached; entire function fails
				if (depth > boardInfo.maxRecursionDepth) {
					results.alwaysSafeCells = [];
					results.alwaysFlaggedCells = [];
					results.numFlags = [];
					return;
				}

				// Base case: If any cell is overflagged, this configuration fails
				if (hintCells.find(c => c.overflagged)) return;

				// Base case: if all hints satisfied
				if (cluster.hintCells.every(c => c.neighborFlags === c.neighborMines)) {

					// If used more flags than are available, this configuration fails
					if (boardInfo.numFlags > boardInfo.numMines) return;

					// Configuration succeeded, write number of flags used for this configuration
					results.numFlags.push(boardInfo.numFlags);

					// If cell is flagged, remove it from always safe
					results.alwaysSafeCells.filter(c => c.flagged).forEach(c => {
						results.alwaysSafeCells.splice(results.alwaysSafeCells.indexOf(c), 1);
					});

					// If cell is safe, remove it from always flagged
					results.alwaysFlaggedCells.filter(c => !c.flagged).forEach(c => {
						results.alwaysFlaggedCells.splice(results.alwaysFlaggedCells.indexOf(c), 1);
					});

					return;
				}

				// General case: make guesses

				// Find a hint cell that is incomplete
				const cell = cluster.hintCells.find(c => c.neighborFlags < c.neighborMines);
				const flagsNeeded = cell.neighborMines - cell.neighborFlags;

				// Find every combination of flags to complete this hint
				const flagCombinations = getPowerSet(cell.neighbors.filter(n => n.unknown), flagsNeeded, flagsNeeded);

				// For every combination of flags that can complete this hint
				for (const flagCombination of flagCombinations) {

					// Add flags
					for (const cellToFlag of flagCombination) flagCell(cellToFlag);

					// Recursively guess the next hint, and update results accordingly
					deduceOneCluster(cluster, results, depth + 1);

					// Remove flags
					for (const cellToFlag of flagCombination) unflagCell(cellToFlag);

					// If no deduction can be made, propagate failure
					if (results.alwaysSafeCells.length === 0 && results.alwaysFlaggedCells.length === 0) return;
				}
			}

			// Returns all possible subsets of an array, with a specified min/max size of each subset
			function getPowerSet(array, minSizeOfSubset = 0, maxSizeOfSubset = Infinity) {
				const subsets = [[]];
				
				for (const element of array) {
					const last = subsets.length - 1;
					for (let i = 0; i <= last; i++) {
						if (subsets[i].length >= maxSizeOfSubset) continue;
						subsets.push([...subsets[i], element]);
					}
				}
				
				return subsets.filter(element => element.length >= minSizeOfSubset);
			}

			// Removes any cluster whose hint cells is a subset of another cluster
			// If more than 1 cluster have the same hint cells, they are identical; 1 of the clusters remains
			function removeSubsetClusters(clusters) {
				const newClustersArray = [];
				let madeChanges;
				
				do {
					madeChanges = false;
					for (const a of clusters) {
						for (const b of clusters) {
							if (a !== b && a.hintCells.every(c => b.hintCells.includes(c))) {
								// a is a subset of b
								clusters.splice(clusters.indexOf(a), 1);
								madeChanges = true;
								break;
							}
						}
						if (madeChanges) break;
					}
				} while (madeChanges);

				return clusters;
			}


			// Each hint cell has its own cluster,
			// together with all hint cells that are connected to the main hint cell by a shared unknown cell
			// i.e. if you can go from: main hint cell -> unknown neighbor -> other hint cell, then other hint cell is in the cluster
			function findClustersToDeduce () {
				let clusters = [];

				// Create a cluster out of each hintCell
				for (cell of hintCells) {

					let isMarkedForDeduction = false;
					const clusterHintCells = [cell];
					const clusterUnknownCells = [];

					// Add this cell's [neighboring unknowns]'s [neighboring hints]
					cell.neighbors.filter(n1 => n1.unknown).forEach(n1 => {
						n1.neighbors.filter(n2 => n2 !== cell && hintCells.includes(n2)).forEach(n2 => {
							clusterHintCells.push(n2);
						});
					});

					// Find all unknown neighbors of hint cells in the cluster
					clusterHintCells.forEach(c => {
						clusterUnknownCells.push(...c.neighbors.filter(n => n.unknown));
					});

					// If no cell in this cluster is marked for deduction, ignore this cluster
					if (!clusterHintCells.some(c => c.markedForDeduction) && !clusterUnknownCells.some(c => c.markedForDeduction)) continue;

					const cluster = {
						// Remove duplicates
						hintCells: [...new Set(clusterHintCells)],
						unknownCells: [...new Set(clusterUnknownCells)],
					};

					clusters.push(cluster);
				}

				return removeSubsetClusters(clusters);
			}

			// Put obvious flags (where a number N only has N unknown cells around it)
			// If changes are made: return true; else return false
			function doEasyFlags () {
				let madeChanges = false;
				for (const cell of hintCells) {
					// Find all unrevealed neighbor cells
					const unrevealedNeighborCells = cell.neighbors.filter(n => !n.revealed);
					// If hint == # of unknown neighbor cells
					if (cell.neighborMines === unrevealedNeighborCells.length) {
						// Flag all unknown neighbor cells
						unrevealedNeighborCells.filter(n => n.unknown).forEach(n => {
							if (handleRightClick(n.row, n.col)) madeChanges = true;
						});
					}
				}
				return madeChanges;
			}

			// Click all numbers that have enough flags around it
			function doEasyOpens () {
				let madeChanges = false;
				allCells.filter(c => c.revealed && c.neighborMines === c.neighborFlags).forEach(c => {
					if (handleLeftClick(c.row, c.col)) madeChanges = true;
				});
				return madeChanges;
			}

			// If finished flagging the whole board, reveal every unknown cell
			function doCleanUp () {
				let madeChanges = false;
				if (boardInfo.numFlags === boardInfo.numMines) {
					allCells.filter(c => c.unknown).forEach(c => {
						if (c.unknown && handleLeftClick(c.row, c.col)) madeChanges = true;
					});
				}
				return madeChanges;
			}

			// Find all hints that, when completed, automatically complete another hint
			function doAutoComplete () {
				let madeChanges = false;
				// For each hint:
				for (const cell of hintCells) {
					// For each uncompleted neighbor hint
					for (const ncell of cell.neighbors.filter(n => n.revealed && n.neighborMines - n.neighborFlags > 0)) {
						// ncell must have equal or lesser effective value (hint - flags) compared to cell
						if (ncell.neighborMines - ncell.neighborFlags > cell.neighborMines - cell.neighborFlags) continue;
						// Calculate if completing cell autocompletes ncell
						// If (cell's unknown neighbors which are not neighboring ncell === cell effective hint - ncell effective hint)
						// then cell autocompletes ncell
						if (cell.neighbors.filter(n => n.unknown && !ncell.neighbors.includes(n)).length === (cell.neighborMines - cell.neighborFlags) - (ncell.neighborMines - ncell.neighborFlags)) {
							for (const cellToFlag of cell.neighbors.filter(n => n.unknown && !ncell.neighbors.includes(n))) {
								if (handleRightClick(cellToFlag.row, cellToFlag.col)) madeChanges = true;
							}
							for (const cellToOpen of ncell.neighbors.filter(n => n.unknown && !cell.neighbors.includes(n))) {
								if (cellToOpen.unknown && handleLeftClick(cellToOpen.row, cellToOpen.col)) madeChanges = true;
							}
						}
					}
				}
				return madeChanges;
			}

			function getRandomElement (array) {
				return array[Math.floor(Math.random() * array.length)];
			}

			// Attempts to take unknown mines, and put them in all the unknown cells around a given hint cell
			function tryToFillHint (hintCell) {
				// unknownFarCells: unknown cells that are not neighboring hintCell
				const unknownFarCells = allCells.filter(c => c.unknown && !hintCell.neighbors.includes(c));

				// If there are not enough mines to bring to this hint, cannot choose fill
				const availableMinesToBring = unknownFarCells.filter(c => c.mine);
				const cellsToAddMine = hintCell.neighbors.filter(n => n.unknown && !n.mine);
				const cellsToFlag = hintCell.neighbors.filter(n => n.unknown);
				if (availableMinesToBring.length < cellsToAddMine.length) return false;

				// // Don't pick the same choice N+1 times in a row
				// if (boardInfo.lastNPerturbChoices.every(s => s === 'fill')) return false;
				
				// Try fill
				const cellsToRemoveMine = shuffleArray(availableMinesToBring).slice(0, cellsToAddMine.length);

				// Surround hint with mines (only the unknown cells), and remove from other places
				cellsToAddMine.forEach(c => putMine(c));
				cellsToRemoveMine.forEach(c => removeMine(c));

				// If this just created a new undeducible hidden region, undo it; fill attempt failed
				if (existsUndeducibleHiddenRegion()) {
					cellsToAddMine.forEach(c => removeMine(c));
					cellsToRemoveMine.forEach(c => putMine(c));
					return false;
				}

				// Fill succeeded
				return true;
			}

			// Attempts to remove unknown mines around a given hint cell, and put those mines in other unknown cells
			function tryToClearHint (hintCell) {
				// unknownFarCells: unknown cells that are not neighboring hintCell
				const unknownFarCells = allCells.filter(c => c.unknown && !hintCell.neighbors.includes(c));

				// If there are not enough cells to move mines away to, cannot choose clear
				const availableCellsToGiveMines = unknownFarCells.filter(c => !c.mine);
				const cellsToRemoveMine = hintCell.neighbors.filter(n => n.unknown && n.mine);
				if (availableCellsToGiveMines.length < cellsToRemoveMine.length) return false;

				// // Don't pick the same choice N+1 times in a row
				// if (boardInfo.lastNPerturbChoices.every(s => s === 'clear')) return false;

				// Try clear
				const cellsToAddMine = shuffleArray(availableCellsToGiveMines).slice(0, cellsToRemoveMine.length);

				// Clear all mines around hint, and add mines elsewhere
				cellsToRemoveMine.forEach(c => removeMine(c));
				cellsToAddMine.forEach(c => putMine(c));

				// If this just created a new undeducible hidden region, undo it; clear attempt failed
				if (existsUndeducibleHiddenRegion()) {
					cellsToAddMine.forEach(c => removeMine(c));
					cellsToRemoveMine.forEach(c => putMine(c));
					return false;
				}
				
				// Clear succeeded
				return true;
			}

			// Fill hint: fill the unknown squares around a hint with mines, so that the hint is now completed
			// Clear hint: remove the unknown (unflagged) mines from around a hint, so that the hint is gone
			function randomlyFillOrClearHint () {

				for (const hintCell of shuffleArray(hintCells)) {
					const choices = shuffleArray(['fill', 'clear']);
					let choiceWorked = false;

					// Try each choice until success
					while (choices.length > 0) {
						const choice = choices.pop();

						// Attempt to do the action of choice
						if (choice === 'fill' && tryToFillHint(hintCell)) {
							choiceWorked = true;
						}
						if (choice === 'clear' && tryToClearHint(hintCell)) {
							choiceWorked = true;
						}

						if (choiceWorked) {
							// boardInfo.lastNPerturbChoices.shift();
							// boardInfo.lastNPerturbChoices.push(choice);
							return true;
						}
					}
				}

				// Failed to do an action
				return false;
			}

			// Finds undeducible mines, and moves them to random other spots, in hopes of helping the board become deducible
			function moveUndeducibleMines () {

				let madeChanges = false;
				let attempts = 0;
				const maxAttempts = 50;

				// Repeat until the max number of attempts has been reached
				while (true) {
					attempts++;
					if (attempts > maxAttempts) return false;

					// Find undeducible mines: mines that can move to a neighbor spot without changing any hints
					const undeducibleMines = findUndeducibleMines();
					workTimes.numRemines += undeducibleMines.length;
					
					// If there are no undeducible mines, then we are done
					if (undeducibleMines.length === 0) return madeChanges;

					// For each undeducible mine
					for (originalMine of undeducibleMines) {
					
						// Find a new location for this mine
						let newMine = getRandomElement(allCells.filter(c => c.unknown && !c.mine && c.canBeMine));

						// If no possible locations to move the mine to, this function fails entirely
						if (!newMine) return false;

						// Found a location; move mine to new location
						removeMine(originalMine);
						putMine(newMine);
						madeChanges = true;
					}
				}

				// Should never reach this line
				throw new Error('moveUndeducibleMines reached unreachable code');
			}

			// Finds which mines can move to a neighbor spot without affecting any hints; these are undeducible
			function findUndeducibleMines () {

				const undeducibleMines = [];

				// For each mine
				for (originalMine of allCells.filter(c => c.mine && !c.flagged)) {

					// Check if it moving it to a neighbor spot will change any hints
					const neighbors1 = originalMine.neighbors;
					for (newMine of originalMine.neighbors.filter(n => !n.mine && !n.flagged)) {
						const neighbors2 = newMine.neighbors;

						// Remember what hints were
						const originalHints = [...neighbors1, ...neighbors2].filter(n => n !== originalMine && n !== newMine && !n.mine).map(n => n.neighborMines);

						// Move mine to new location
						removeMine(originalMine);
						putMine(newMine);

						// Check if hints are still the same
						const newHints = [...neighbors1, ...neighbors2].filter(n => n !== originalMine && n !== newMine && !n.mine).map(n => n.neighborMines);
						const hintsIdentical = newHints.every((hint, index) => hint === originalHints[index]);

						// Move mine back to original location
						putMine(originalMine);
						removeMine(newMine);

						// If all hints (besides the old/new mine locations) remain identical, then this mine is undeducible
						if (hintsIdentical) {
							undeducibleMines.push(originalMine);
							// Check the next mine
							break;
						}
					}
				}

				return undeducibleMines;
			}

			// Checks if there is a region that is separated from the starting cell by a wall of mines,
			// where the separated region contains both mines and safe squares, making it undeducible
			function existsUndeducibleHiddenRegion () {
				let time1 = Date.now();
				const [safeCluster, visibleMines] = expandSafeCluster();
				workTimes.hiddenRegions += Date.now() - time1;

				// If the safeCluster contains all the safe cells, then there is no undeducible hidden region
				if (safeCluster.length === boardInfo.rows * boardInfo.cols - boardInfo.numMines) return false;

				// If the visibleMines contains all the mine cells, then there is no undeducible hidden region
				if (visibleMines.length === boardInfo.numMines) return false;

				// Else, this means there is a hidden region with both safe cells and mines (undeducible)
				return true;
			
				// Recursively finds a safe (non-mine) cluster, connected to the starting clicked cell
				// Returns array of cells in the safe cluster, and array of mines that touch this safe cluster
				function expandSafeCluster (cell = allCells.find(c => !c.canBeMine), safeCluster = [], visibleMines = []) {
					
					// If this cell is safe, we may want it in the cluster
					if (!cell.mine) {

						// If this cell is already in the cluster, do nothing
						if (safeCluster.includes(cell)) return [safeCluster, visibleMines];

						// Add it to the cluster
						safeCluster.push(cell);

						// Check if its neighbors should be added
						cell.neighbors.forEach(n => {expandSafeCluster(n, safeCluster, visibleMines)});
					}

					// This is a mine that touches the safe cluster
					else {

						// If this cell is already in neighbors, do nothing
						if (visibleMines.includes(cell)) return [safeCluster, visibleMines];

						// Add it to neighbors
						visibleMines.push(cell);
					}

					return [safeCluster, visibleMines];
				}
			}


			// For debugging
			function showMines () {
				allCells.filter(c => c.mine).forEach(c => c.exploded = true);
				renderBoard(true);
			}

			function hideMines () {
				allCells.filter(c => c.mine).forEach(c => c.exploded = false);
				renderBoard(true);
			}

			function cellToJQueryElement (cell) {
				return $('.square[data-row=' + cell.row + '][data-col=' + cell.col + ']');
			}

			function cellToCoordinates (cell) {
				return '(' + cell.row + ', ' + cell.col + ')';
			}

			// When you hold left click on an uncompleted hint, all the unknown cells around it "reveal" for a moment
			function previewNeighbors (cell) {
				const unknownNeighbors = cell.neighbors.filter(n => n.unknown);

				// Render previewed neighbor unknown cells
				unknownNeighbors.forEach(n => {
					n.previewing = true;
					n.markedForRendering = true;
				});
				renderBoard();

				// Set mouseup and mouseout to unpreview neighbors
				cellToJQueryElement(cell).mouseup(unpreviewNeighbors).mouseout(unpreviewNeighbors);
				
				function unpreviewNeighbors () {
					unknownNeighbors.forEach(n => {
						n.markedForRendering = true;
						n.previewing = false;
					});
					renderBoard();
				}
			}





			/*
			TODO: 
			- change deduceclusters to virtual recursion to not pass so many variables

			MAYBE:
			- for fill and clear, have a preference for choosing to move mines that are on the horizon??
			- once the board is done generating, try a full deduce on it to see if it actually works
				
			*/




			createBoard();
			savestates.save();
			renderBoard(true);
			timer.render();
		</script>

	</body>
</html>
