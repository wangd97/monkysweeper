<!DOCTYPE html>
<html>
	<head>
		<title>Monkysweeper</title>
		<link rel="icon" type="image/x-icon" href="favicon.ico">
		<link rel="stylesheet" href="lib/style.css">
		<link rel="preconnect" href="https://fonts.googleapis.com">
		<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
		<link href="https://fonts.googleapis.com/css2?family=Nunito:wght@200;300&family=Shantell+Sans:wght@300&family=Tilt+Neon&display=swap" rel="stylesheet">
		<script src="lib/jquery.js"></script>
		<script src="lib/seedrandom.js"></script>
		<script>Math.seedrandom(0);</script>
	</head>

	<body>

		<div id="container">
			<table id="board"></table>
			<div id="info"></div>
			<div id="timer" style="color:white"></div>
		</div>


		<script>









			/*
			let startTime; // The timestamp when the timer started
			let elapsedTime = 0; // The total elapsed time in milliseconds
			let timerInterval; // The ID of the setInterval() function that updates the timer

			function startTimer () {
				startTime = Date.now(); // Record the current timestamp
				timerInterval = setInterval(updateTimer, 100); // Update the timer every 100 milliseconds
			}

			function stopTimer () {
				clearInterval(timerInterval); // Stop the setInterval() function
			}

			function updateTimer () {
				// Calculate the elapsed time since the timer started
				const currentTime = Date.now();
				elapsedTime = currentTime - startTime;

				// Update the timer display with the new elapsed time
				const timerDisplay = document.getElementById('timer');
				timerDisplay.innerText = formatTime(elapsedTime);
			}

			function formatTime (time) {
				// Format the time in the format mm:ss
				const minutes = Math.floor(time / 60000);
				const seconds = Math.floor((time % 60000) / 1000);
				return `${minutes.toString().padStart(2, '0')}:${seconds.toString().padStart(2, '0')}`;
			}
			*/



			$("#board").on("contextmenu", function() {return false;});
			var audio = new Audio('audio/Roblox-death-sound.mp3');

			let boardData = {};
			let board = [];
			let allCells = [];
			let wantDeducible = false;

			// Reset all default data
			function cleanBoard () {
				boardData = {
					rows: 16,
					cols: 30,
					numMines: 170,
					numFlags: 0,
					isGameStarted: false,
					isGameOver: false,
					won: false,
					lost: false,
					// for making board deducible
					lastNPerturbanceChoices: ['', '', ''],
					globalDeductionMineThreshold: 15
				};

				board = [];
				allCells = [];
				for (let i = 0; i < boardData.rows; i++) {
					board[i] = [];
					for (let j = 0; j < boardData.cols; j++) {
						board[i][j] = {
							row: i,
							col: j,
							isMine: false,
							revealed: false,
							flagged: false,
							neighborMines: 0,
							neighborFlags: 0,
							neighbors: [],
							exploded: false,
							// for making board deducible
							markedForDeduction: false
						};
						allCells.push(board[i][j]);
					}
				}
				initNeighbors();
				updateAllCells();
			}

			function updateAllCells () {
				allCells = [];
				for (let i = 0; i < boardData.rows; i++) {
					for (let j = 0; j < boardData.cols; j++) {
						allCells.push(board[i][j]);
					}
				}
			}

			function startGame (clickedRow, clickedCol) {
				workTimes.total = Date.now();
				while (true) {
					cleanBoard();
					boardData.isGameStarted = true;
					if (initMineLocations(clickedRow, clickedCol)) break;
				}
				handleLeftClick(clickedRow, clickedCol);
				renderBoard();
			}

			// Make the board deducible by simulating a playthrough, and making changes if necessary
			// Returns true if success, else returns false
			function initMineLocations (clickedRow, clickedCol) {

				do {
					cleanBoard();
					boardData.isGameStarted = true;
					initRandomMineLocations(clickedRow, clickedCol);
					handleLeftClick(clickedRow, clickedCol);
				} while (existsUndeducibleHiddenRegion());

				let iterations = 0;
				if (true) {
					// Repeatedly deduce and perturb mines
					while (deduce() || perturb());

					// If failed to generate deducible board, return false
					if (!boardData.won) {
						workTimes.retry++;
						console.log('retry');
						return false;
					}
				}

				// Remember mine locations of deducible board
				const mineCells = allCells.filter(c => c.isMine);

				// Reset board
				cleanBoard();
				boardData.isGameStarted = true;

				// Place mines on the board
				mineCells.forEach(c => {board[c.row][c.col].isMine = true});
				updateMineInfo();

				return true;
			}

			// Generate random mine locations
			// Avoid the clicked cell and its neighbors
			// Always returns true;
			function initRandomMineLocations (clickedRow, clickedCol) {
				const clickedCell = board[clickedRow][clickedCol];

				// Mines can be placed on any cell except for the clicked cell and its neighbors
				const possibleMineCells = allCells.filter(c => c !== clickedCell && !clickedCell.neighbors.includes(c));

				// Shuffle possibleMineCells and pick the first N cells to have a mine
				shuffleArray(possibleMineCells).slice(0, boardData.numMines).forEach(cell => {cell.isMine = true});

				// // For adding custom mine locations
				// 	// rows: 4,
				// 	// cols: 7,
				// 	// numMines: 7,
				// allCells.forEach(c => c.isMine = false);
				// board[0][0].isMine = true;
				// board[1][1].isMine = true;
				// board[1][2].isMine = true;
				// board[1][3].isMine = true;
				// board[1][4].isMine = true;
				// board[1][5].isMine = true;
				// board[0][6].isMine = true;

				updateMineInfo();

				return true;
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

			// init each square with the number of adjacent mines
			function initNeighbors () {
				for (const cell of allCells) {
					cell.neighbors = [];

					// Check the neighboring squares
					for (let rowShift = -1; rowShift <= 1; rowShift++) {
						for (let colShift = -1; colShift <= 1; colShift++) {
							const row = cell.row + rowShift;
							const col = cell.col + colShift;

							// Neighbor must be a legal cell:
							if (row < 0 || row >= boardData.rows || col < 0 || col >= boardData.cols) continue;

							// Neighbor must not be the cell itself
							if (row === cell.row && col === cell.col) continue;

							// Add this neighbor to the list of neighbors
							cell.neighbors.push(board[row][col]);
						}
					}
				}
			}

			// Update the total number of flags, and the number of neighboring flags for each cell
			function updateFlagInfo () {
				let numFlags = 0;
				for (cell of allCells) {
					if (cell.flagged) numFlags++;
					cell.neighborFlags = cell.neighbors.filter(n => n.flagged).length;
				}
				boardData.numFlags = numFlags;
			}

			// Update all the hint numbers
			function updateMineInfo () {
				for (cell of allCells) {
					cell.neighborMines = cell.neighbors.filter(n => n.isMine).length;
				}
			}

			// Handle clicking on a cell
			// Returns true if a change was made, false otherwise
			function handleLeftClick (row, col) {

				// Game must not be over
				if (boardData.isGameOver) return false;

				// If the game has not started yet, generate the board
				if (!boardData.isGameStarted) {
					startGame(row, col);
					return true;
				}

				const cell = board[row][col];

				// If square is already revealed
				if (cell.revealed) {
					// If Flags == Mines, then can reveal surrounding squares
					let madeChanges = false;
					if (cell.neighborFlags == cell.neighborMines) {
						cell.neighbors.filter(neighborCell => !neighborCell.flagged && !neighborCell.revealed).forEach(neighborCell => {
							if (handleLeftClick(neighborCell.row, neighborCell.col)) madeChanges = true;
						});
					}
					return madeChanges;
				}

				// If square is flagged: do nothing
				if (cell.flagged) return false;

				// If square is not revealed and not flagged:
				if (!cell.revealed && !cell.flagged) {

					// Reveal the square
					cell.revealed = true;

					// Mark this square's neighbors for deduction
					cell.neighbors.forEach(n => n.markedForDeduction = true);

					// Check for game over
					if (cell.isMine) {
						lose(row, col);
					}

					// If the square has no neighboring mines, reveal its neighbors
					if (cell.neighborMines == 0) {
						cell.neighbors.filter(cell => !cell.revealed).forEach(cell => handleLeftClick(cell.row, cell.col));
					}

					// Check for victory
					if (checkVictory()) {
						victory();
					}

					return true;
				}
			}

			// Handle right-clicking to flag a square
			// Returns true if a change was made, false otherwise
			function handleRightClick (row, col) {
				const cell = board[row][col];

				// Game must be started
				if (!boardData.isGameStarted) return false;

				// Cannot flag a revealed square
				if (cell.revealed) return false;

				// Toggle the flagged status of the square
				cell.flagged = !cell.flagged;
				updateFlagInfo();

				// Mark this square's neighbors for deduction
				cell.neighbors.forEach(n => n.markedForDeduction = true);

				return true;
			}

			// Handle lose
			function lose (clickedRow, clickedCol) {
				boardData.isGameOver = true;
				boardData.lost = true;
				
				// Show all the mines locations, using flags
				board[clickedRow][clickedCol].revealed = true;
				board[clickedRow][clickedCol].exploded = true;

				// Game over message
        		audio.play();
				console.log('Game over!');
			}

			// Check if all non-mine squares are revealed
			function checkVictory () {
				return allCells.filter(c => c.revealed).length === boardData.rows * boardData.cols - boardData.numMines;
			}

			// Handle victory
			function victory () {
				boardData.isGameOver = true;
				boardData.won = true;
				// Reveal all the squares and display the victory message
				for (const cell of allCells) {
					if (cell.isMine && !cell.flagged) {
						handleRightClick(cell.row, cell.col);
					}
					if (!cell.revealed && !cell.isMine) {
						handleLeftClick(cell.row, cell.col);
					}
				}

				console.log('Congratulations, you won!');
				workTimes.total = Date.now() - workTimes.total;
				console.log(workTimes);
				// TODO: uncomment the line below when Dylan FINALLY fixes his stuff smh
        		// window.open('https://www.youtube.com/watch?v=ymdhRMiMGK0&ab_channel=GrimGriefer', 'popup', config='height=375,width=450')
			}

			function renderBoard () {
				if (!boardData.isGameStarted) {
					renderBoardBeforeGameStarted();
				}
				else {
					renderBoardAfterGameStarted();
				}
				$('#info').html('Mines Remaining: ' + (boardData.numMines - boardData.numFlags));
			}

			// Create a fully covered board with event listeners
			function renderBoardBeforeGameStarted () {

				// Draw table
				$("#board").empty();
				for (let i = 0; i < boardData.rows; i++) {
					let $tr = $('<tr></tr>');
					$("#board").append($($tr));
					for (let j = 0; j < boardData.cols; j++) {
						let $td = $('<td class="square covered" data-row="' + i + '" data-col="' + j + '"></td>');
						$tr.append($($td));
					}
				}

				// Set up event listeners for each square
				$('.square').each((index, square) => {
					const LEFT_CLICK = 1;
					const RIGHT_CLICK = 3;

					const i = parseInt($(square).attr('data-row'));
					const j = parseInt($(square).attr('data-col'));

					$(square).on('mousedown', (e) => {
						if (e.which == LEFT_CLICK) {
							if (handleLeftClick(i, j)) {
								savestates.save();
								renderBoard();
							}
						}
						if (e.which == RIGHT_CLICK) {
							if (handleRightClick(i, j)) {
								savestates.save();
								renderBoard();
							}
						}
					});
				});
			}

			// Render board HTML
			function renderBoardAfterGameStarted () {

				for (const cell of allCells) {
					
					// Find the td element (square) of this row and col
					const $td = $('td[data-row=' + cell.row + '][data-col=' + cell.col + ']');

					// Reset classes
					$td.attr('class', 'square');

					// // Temp
					// if (cell.isMine) {
					// 	$td.addClass('mine');
					// }

					// Render visual state of this square
					if (cell.revealed) {
						$td.addClass('revealed');
						if (!cell.isMine && cell.neighborMines > 0 && cell.neighborFlags > cell.neighborMines)
							$td.addClass('overflagged');
						if (cell.isMine)
							$td.addClass('mine').addClass('exploded');
					}
					if (!cell.revealed) {
						$td.addClass('covered');
						if (!cell.revealed && cell.flagged)
							$td.addClass('flagged');
					}

					// Render hint number of this square
					if (cell.revealed && !cell.isMine && cell.neighborMines > 0) {
						$td.html(cell.neighborMines);
						$td.css('color', 'var(--color' + cell.neighborMines + ')');
					}
					else {
						$td.empty();
					}
				}
			}






			// Undos

			$(document).keydown(function(e) {
				// Q
				if (e.which === 81) {
					deduce();
					savestates.save();
					renderBoard();
				}
				// W
				if (e.which === 87) {
					perturb();
					savestates.save();
					renderBoard();
				}
				// E
				if (e.which === 69) {
					initSolver();
					savestates.save();
					doLocalDeduction();
					renderBoard();
				}
				// Ctrl + Z
				if (e.which === 90 && e.ctrlKey) {
					savestates.undo();
					renderBoard();
				}
				// Ctrl + Y
				if (e.which === 89 && e.ctrlKey) {
					savestates.redo();
					renderBoard();
				}
			});

			// Savestates
			let savestates = {
				states: [],
				index: -1,
				save: function() {
					this.index++;
					this.states[this.index] = {
						boardData: structuredClone(boardData),
						board: structuredClone(board)
					};
					this.states.length = this.index + 1;
				},
				undo: function() {
					if (this.index <= 0) return;
					this.index--;
					boardData = structuredClone(this.states[this.index].boardData);
					board = structuredClone(this.states[this.index].board);
					updateAllCells();
				},
				redo: function() {
					if (this.index + 1 >= this.states.length) return;
					this.index++;
					boardData = structuredClone(this.states[this.index].boardData);
					board = structuredClone(this.states[this.index].board);
					updateAllCells();
				}
			};



			let workTimes = {
				hiddenRegions: 0,
				retry: 0,
				initSolver: 0,
				doEasyDeduction: 0,
				doAutoComplete: 0,
				local: 0,
				localsuccess: 0,
				global: 0,
				globalsuccess: 0,
				clustering: 0,
				helper: 0,
				perturb: 0,
				total: 0,
				clusters: 0,
				maxDepth: 0
			}
			let time = 0;

			let hintCells = [];

			function deduce () {
				// savestates.save();
				// console.log('q');

				// Setup
				let time = Date.now();
				if (!initSolver()) return false;
				workTimes.initSolver += Date.now() - time;

				// Deduce flags
				time = Date.now();
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
				if (boardData.numMines - boardData.numFlags <= boardData.globalDeductionMineThreshold && doGlobalDeduction()) {
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

				// Setup
				time = Date.now();
				if (!initSolver()) return false;
				workTimes.initSolver += Date.now() - time;

				let thisTime = Date.now();

				let madeChanges = false;

				// Perturb mines
				if (hintCells.length > 0) {
					madeChanges = randomlyFillOrClearHint();
				}
				// Fail to make a change
				workTimes.perturb += Date.now() - thisTime;

				return madeChanges;
			}

			function initSolver () {
				// Board must be generated (after the first click)
				if (!boardData.isGameStarted) return false;
				if (boardData.isGameOver) return false;

				// Add data to board
				for (const cell of allCells) {
					cell.alwaysFlagged = true;
					cell.alwaysSafe = true;
				}
				
				// hintCells: every cell that has a number and has a covered unflagged neighbor cell
				hintCells = allCells.filter(c => c.revealed && c.neighborMines > 0 && c.neighbors.some(n => !n.revealed && !n.flagged));
				
				return true;
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

				// Deduced all clusters; unmark all for deduction
				allCells.forEach(c => c.markedForDeduction = false);

				let [madeChanges, alwaysReachesFlagLimit] = deduceClusters(clusters);
				return madeChanges;
			}

			// This function deduces the flags and safe cells for one cluster that contains the entire board's hints, and their neighboring unknown cells
			// This is slow; use this near the end of the game when there are few mines remaining
			function doGlobalDeduction () {

				// All incomplete hints and all neighboring unknown cells go in the same cluster
				const unknownCells = [];
				hintCells.forEach(c => unknownCells.push(...c.neighbors.filter(n => !n.revealed && !n.flagged)));
				const clusters = [
					{
						hintCells: hintCells,
						unknownCells: [...new Set(unknownCells)]
					}
				];

				// Deduced all clusters; unmark all for deduction
				allCells.forEach(c => c.markedForDeduction = false);

				let [madeChanges, alwaysReachesFlagLimit] = deduceClusters(clusters);

				// If flag limit is always reached, then every unknown cell, that does not neighbor the hints, can be opened
				if (alwaysReachesFlagLimit) {
					const hiddenCells = allCells.filter(c => !c.revealed && !c.flagged && !unknownCells.includes(c));
					hiddenCells.forEach(c => {
						if (!c.revealed && handleLeftClick(c.row, c.col)) madeChanges = true;
					});
				}
				return madeChanges;
			}


			let bigCallCounter = 0;
			let tempTime = 0;
			// Try every way to fill up cluster with flags
			// If a cell is unflagged in every configuration, then we can open it
			function deduceClusters (clusters) {
				let madeChanges = false;
				let alwaysReachesFlagLimit = false;

				bigCallCounter++;

				// For each cluster: find every combination of flags that satisfies all hints in the cluster
				for (const cluster of clusters) {

					const alwaysSafeCells = [...cluster.unknownCells];
					const alwaysFlaggedCells = [...cluster.unknownCells];

					// Try every legal combination of flags
					let time2 = Date.now();
					alwaysReachesFlagLimit = deduceOneCluster(cluster, alwaysSafeCells, alwaysFlaggedCells) ||  alwaysReachesFlagLimit;
					workTimes.helper += Date.now() - time2;

					// If a cell is always safe, open it; or always flagged, flag it
					alwaysSafeCells.forEach(c => {
						if (!c.revealed && handleLeftClick(c.row, c.col)) madeChanges = true;
					});
					alwaysFlaggedCells.forEach(c => {
						if (!c.flagged && handleRightClick(c.row, c.col)) madeChanges = true;
					});

				}

				return [madeChanges, alwaysReachesFlagLimit];
			}

			let callCounter = 0;

			let depth = 1;
			// Recursively makes every valid flag guess for a cluster
			// Pass in alwaysSafeCells and alwaysFlaggedCells are arrays that get modified in place
			// Returns true if all flag configurations reach the flag limit, else returns false
			function deduceOneCluster (cluster, alwaysSafeCells, alwaysFlaggedCells, alwaysReachesFlagLimit = true) {
				callCounter++;

				// Base case: if all hints satisfied and did not exceed flag limit: update "always flagged" and "always safe"
				if (cluster.hintCells.every(c => c.neighborFlags === c.neighborMines)) {

					// If used more flags than are available, this configuration fails
					if (boardData.numFlags > boardData.numMines) return alwaysReachesFlagLimit;

					// Configuration succeeded; mark which cells are no longer always safe or always flagged

					// If cell is flagged, remove it from always safe
					alwaysSafeCells.filter(c => c.flagged).forEach(c => {
						alwaysSafeCells.splice(alwaysSafeCells.indexOf(c), 1);
					});

					// If cell is safe, remove it from always flagged
					alwaysFlaggedCells.filter(c => !c.flagged).forEach(c => {
						alwaysFlaggedCells.splice(alwaysFlaggedCells.indexOf(c), 1);
					});

					// If did not reach the flag limit, return false; therefore alwaysReachesFlagLimit is false
					if (boardData.numFlags !== boardData.numMines) return false;
					
					// If the flag limit was reached, return the current boolean (default to true)
					return alwaysReachesFlagLimit;
				}

				// Base case: If any cell has too many flags, this configuration fails
				if (hintCells.find(c => c.neighborFlags > c.neighborMines)) return alwaysReachesFlagLimit;

				// General case: make guesses

				// Find a hint cell that is incomplete
				const cell = cluster.hintCells.find(c => c.neighborFlags < c.neighborMines);
				const flagsNeeded = cell.neighborMines - cell.neighborFlags;

				// Find every combination of flags to complete this hint
				const flagCombinations = getPowerSet(cell.neighbors.filter(n => !n.revealed && !n.flagged), flagsNeeded, flagsNeeded);

				// Try every legal flag combination
				for (const flagCombination of flagCombinations) {

					// Flag all suggested cells, to complete this hint
					for (const cellToFlag of flagCombination) cellToFlag.flagged = true;
					updateFlagInfo();

					// Recurse to guess the next hint
					depth++;
					if (depth > workTimes.maxDepth) workTimes.maxDepth = depth;
					alwaysReachesFlagLimit = deduceOneCluster(cluster, alwaysSafeCells, alwaysFlaggedCells, alwaysReachesFlagLimit);
					depth--;

					// Unflag these cells
					for (const cellToFlag of flagCombination) cellToFlag.flagged = false;
					updateFlagInfo();
				}

				return alwaysReachesFlagLimit;
			}

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


			// Each hint cell has its own cluster:
			// together with all hint cells that are connected to the main hint cell by a shared unknown cell
			// i.e. if you can go from: main hint cell -> unknown neighbor -> other hint cell, then other hint cell is in the cluster
			function findClustersToDeduce () {
				let clusters = [];

				// // Create a small cluster out of each hintCell (just the hint cell alone)
				// for (cell of hintCells) {
				// 	const cluster = {
				// 		hintCells: [cell],
				// 		unknownCells: cell.neighbors.filter(n => !n.revealed && !n.flagged),
				// 		attemptDeduction: true
				// 	};
				// 	cluster.numUnknownCellsWhenClusterWasCreated = cluster.unknownCells.length;
				// 	clusters.push(cluster);
				// }

				// Create a cluster out of each hintCell
				for (cell of hintCells) {

					let isMarkedForDeduction = false;
					const clusterHintCells = [cell];
					const clusterUnknownCells = [];

					// Add this cell's [neighboring unknowns]'s [neighboring hints]
					cell.neighbors.filter(n1 => !n1.revealed && !n1.flagged).forEach(n1 => {
						n1.neighbors.filter(n2 => n2 !== cell && hintCells.includes(n2)).forEach(n2 => {
							clusterHintCells.push(n2);
						});
					});

					// Find all unknown neighbors of hint cells in the cluster
					clusterHintCells.forEach(c => {
						clusterUnknownCells.push(...c.neighbors.filter(n => !n.revealed && !n.flagged));
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

			// function decideWhichClustersToAttemptDeduction () {
			// 	for (cluster of clusters) {

			// 		// Check if this cluster already existed the last time 
			// 		const oldVersionOfCluster = oldClusters.find(oldCluster =>
			// 			oldCluster.hintCells.length === cluster.hintCells.length
			// 			&& oldCluster.hintCells.every(c => cluster.hintCells.includes(c))
			// 		);

			// 		// If this cluster is brand new, keep the new cluster
			// 		if (!oldVersionOfCluster) continue;

			// 		// If cluster has since been changed, keep the new cluster
			// 		if (oldVersionOfCluster.numUnknownCellsWhenClusterWasCreated !== cluster.numUnknownCellsWhenClusterWasCreated) continue;

			// 		// This cluster already exists and has not changed, do not try and deduce it
			// 		cluster.attemptDeduction = false;
			// 	}
			// }

			// Put obvious flags (where a number N only has N covered cells around it)
			// If changes are made: return true; else return false
			function doEasyFlags () {
				let madeChanges = false;
				for (const cell of hintCells) {
					// Find all covered neighbor cells
					const coveredNeighborCells = cell.neighbors.filter(n => !n.revealed);
					// If hint == # of covered neighbor cells
					if (cell.neighborMines === coveredNeighborCells.length) {
						// Flag all covered neighbor cells
						coveredNeighborCells.filter(n => !n.revealed && !n.flagged).forEach(n => {
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

			function doCleanUp () {
				let madeChanges = false;
				if (boardData.numFlags === boardData.numMines) {
					allCells.filter(c => !c.revealed && !c.flagged).forEach(c => {
						if (handleLeftClick(c.row, c.col)) madeChanges = true;
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
						// If (cell's covered unflagged neighbors which are not neighboring ncell === cell effective hint - ncell effective hint)
						// then cell autocompletes ncell
						if (cell.neighbors.filter(n => !n.revealed && !n.flagged && !ncell.neighbors.includes(n)).length === (cell.neighborMines - cell.neighborFlags) - (ncell.neighborMines - ncell.neighborFlags)) {
							for (const cellToFlag of cell.neighbors.filter(n => !n.revealed && !n.flagged && !ncell.neighbors.includes(n))) {
								if (handleRightClick(cellToFlag.row, cellToFlag.col)) madeChanges = true;
							}
							for (const cellToOpen of ncell.neighbors.filter(n => !n.revealed && !cell.neighbors.includes(n))) {
								if (handleLeftClick(cellToOpen.row, cellToOpen.col)) madeChanges = true;
							}
						}
					}
				}
				return madeChanges;
			}

			function cellToCoordinates (cell) {
				return '(' + cell.row + ', ' + cell.col + ')';
			}

			function getRandomElement (array) {
				return array[Math.floor(Math.random() * array.length)];
			}

			function sum (array) {
				return array.reduce((a, b) => a + b, 0);
			}

			function expandUnknownCluster (cell, cluster = [], neighbors = []) {
				
				// If this cell is unknown, we may want it in the cluster
				if (!cell.revealed && !cell.flagged) {

					// If this cell is already in the cluster, do nothing
					if (cluster.includes(cell)) return [cluster, neighbors];

					// Add it to the cluster
					cluster.push(cell);

					// Check if its neighbors should be added
					cell.neighbors.forEach(n => {expandUnknownCluster(n, cluster, neighbors)});
				}

				// This is a cell that neighbors the cluster
				else {

					// If this cell is already in neighbors, do nothing
					if (neighbors.includes(cell)) return [cluster, neighbors];

					// Add it to neighbors
					neighbors.push(cell);
				}

				return [cluster, neighbors];
			}

			function tryToFillHint (hintCell) {
				// coveredFarCells: unknown cells that are not neighboring hintCell
				const coveredFarCells = allCells.filter(c => !c.revealed && !c.flagged && !hintCell.neighbors.includes(c));

				// If there are not enough mines to bring to this hint, cannot choose fill
				const availableMinesToBring = coveredFarCells.filter(c => c.isMine);
				const cellsToAddMine = hintCell.neighbors.filter(n => !n.revealed && !n.flagged && !n.isMine);
				const cellsToFlag = hintCell.neighbors.filter(n => !n.revealed && !n.flagged);
				if (availableMinesToBring.length < cellsToAddMine.length) return false;

				// Don't pick the same choice N+1 times in a row
				if (boardData.lastNPerturbanceChoices.every(s => s === 'fill')) return false;
				
				// Try fill

				// Surround hint with mines (only the unknown cells)
				cellsToAddMine.forEach(n => n.isMine = true);

				// Remove mines from other places, to correct mine count
				const cellsToRemoveMine = shuffleArray(availableMinesToBring).slice(0, cellsToAddMine.length);
				cellsToRemoveMine.forEach(c => c.isMine = false);

				// If this just created a new undeducible hidden region, undo it; fill attempt failed
				if (existsUndeducibleHiddenRegion()) {
					cellsToAddMine.forEach(n => n.isMine = false);
					cellsToRemoveMine.forEach(c => c.isMine = true);
					return false;
				}

				// Fill succeeded
				updateMineInfo();
				return true;
			}

			function tryToClearHint (hintCell) {
				// coveredFarCells: unknown cells that are not neighboring hintCell
				const coveredFarCells = allCells.filter(c => !c.revealed && !c.flagged && !hintCell.neighbors.includes(c));

				// If there are not enough cells to move mines away to, cannot choose clear
				const availableCellsToGiveMines = coveredFarCells.filter(c => !c.isMine);
				const cellsToRemoveMine = hintCell.neighbors.filter(n => !n.revealed && !n.flagged && n.isMine);
				if (availableCellsToGiveMines.length < cellsToRemoveMine.length) return false;

				// Don't pick the same choice N+1 times in a row
				if (boardData.lastNPerturbanceChoices.every(s => s === 'clear')) return false;

				// Try clear

				// Clear all mines around hint
				cellsToRemoveMine.forEach(n => n.isMine = false);

				// Add mines to correct mine count
				const cellsToAddMine = shuffleArray(availableCellsToGiveMines).slice(0, cellsToRemoveMine.length);
				cellsToAddMine.forEach(c => c.isMine = true);

				// If this just created a new undeducible hidden region, undo it; clear attempt failed
				if (existsUndeducibleHiddenRegion()) {
					cellsToAddMine.forEach(c => c.isMine = false);
					cellsToRemoveMine.forEach(c => c.isMine = true);
					return false;
				}
				
				// Clear succeeded
				updateMineInfo();
				return true;
			}

			function randomlyFillOrClearHint () {

				for (const hintCell of shuffleArray(hintCells)) {
					const choices = shuffleArray(['fill', 'clear']);

					// Try each choice until success
					while (choices.length > 0) {
						const choice = choices[0];
						let choiceWorked = false;

						if (choice === 'fill' && tryToFillHint(hintCell)) {
							choiceWorked = true;
						}
						if (choice === 'clear' && tryToClearHint(hintCell)) {
							choiceWorked = true;
						}

						if (choiceWorked) {
							boardData.lastNPerturbanceChoices.shift();
							boardData.lastNPerturbanceChoices.push(choice);
							return true;
						}
						else {
							choices.shift();
						}
					}
				}

				// Failed to do an action
				return false;
			}

			function existsUndeducibleHiddenRegion () {
				let time1 = Date.now();
				const [safeCluster, visibleMines] = expandSafeCluster();
				workTimes.hiddenRegions += Date.now() - time1;

				// If the safeCluster contains all the safe cells, then there is no undeducible hidden region
				if (safeCluster.length === boardData.rows * boardData.cols - boardData.numMines) return false;

				// If the visibleMines contains all the mine cells, then there is no undeducible hidden region
				if (visibleMines.length === boardData.numMines) return false;

				// Else, this means there is a hidden region with both safe cells and mines (undeducible)
				return true;
			}
			
			function expandSafeCluster (cell = allCells.find(c => c.revealed), safeCluster = [], visibleMines = []) {
				
				// If this cell is safe, we may want it in the cluster
				if (!cell.isMine) {

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





			/*
			TODO: 
			- clicking board[6][4] causes a game over
			- once the board is done generating, try a full deduce on it to see if it actually works
			- for fill and clear, have a preference for choosing to move mines that are on the horizon??
			- no longer have the ability to solve a situation like:
				0000000
				2FFFFF2
				0000000 with 2 bombs remaining
				
			*/



			cleanBoard();
			savestates.save();
			renderBoard();

				
		</script>

	</body>
</html>
