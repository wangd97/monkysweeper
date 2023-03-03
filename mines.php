<!DOCTYPE html>
<html>
	<head>
		<title>Monkysweeper</title>
		<style>
			body{cursor: url('images/banana-covered-cursor.png'), default;}
		</style>
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
			let wantDeducible = true;

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
					maxRecursionDepth: 5
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
							exploded: false
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
					if (wantDeducible) {
						if (initDeducibleMineLocations(clickedRow, clickedCol)) break;
					}
					else {
						if (initRandomMineLocations(clickedRow, clickedCol)) break;
					}
				}
				handleLeftClick(clickedRow, clickedCol);
				renderBoard();
			}

			// Make the board deducible by simulating a playthrough, and making changes if necessary
			// Returns true if success, else returns false
			function initDeducibleMineLocations (clickedRow, clickedCol) {

				initRandomMineLocations(clickedRow, clickedCol);
				handleLeftClick(clickedRow, clickedCol);

				let iterations = 0;
				// Repeatedly deduce and perturb mines
				while (deduce() || perturb());

				// If failed to generate deducible board, return false
				if (!boardData.won) {
					workTimes.retry++;
					return false;
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

				// if (row === 0 && col === 27) {
				// 	throw new Error('error');
				// }

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
					renderBoard();
				}
				// W
				if (e.which === 87) {
					perturb();
					renderBoard();
				}
				// E
				if (e.which === 69) {
					initSolver();
					doHardDeduction();
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
				initSolver: 0,
				doEasyDeduction: 0,
				doAutoComplete: 0,
				doHardDeduction: 0,
				perturb: 0,
				total: 0,
				clusters: 0,
				hardDeductionSuccesses: 0,
				retry: 0,
				maxDepth: 0
			}
			let time = 0;

			let hintCells = [];
			let oldClusters = [];
			let clusters = [];

			function deduce () {
				// savestates.save();
				console.log('q');

				// Setup
				time = Date.now();
				if (!initSolver()) return false;
				workTimes.initSolver += Date.now() - time;

				// Deduce flags
				time = Date.now();
				if (doEasyDeduction()) return true;
				workTimes.doEasyDeduction += Date.now() - time;
				time = Date.now();
				if (doAutoComplete()) return true;
				workTimes.doAutoComplete += Date.now() - time;
				time = Date.now();
				if (doHardDeduction()) {
					workTimes.hardDeductionSuccesses++;
					return true;
				}
				workTimes.doHardDeduction += Date.now() - time;
				// Fail to make a change
				return false;
			}

			function perturb () {
				// savestates.save();
				console.log('w');

				// Setup
				time = Date.now();
				if (!initSolver()) return false;
				workTimes.initSolver += Date.now() - time;

				time = Date.now();

				// Perturb mines
				if (hintCells.length > 0) {
					return randomlyFillOrClearHint();
				}
				// Fail to make a change
				workTimes.perturb += Date.now() - time;
				return false;
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


			let bigCallCounter = 0;
			let tempTime = 0;
			// Try every way to fill up horizon with flags
			// If a cell is unflagged in every configuration, then we can open it
			function doHardDeduction () {
				bigCallCounter++;
				
				let madeChanges = false;

				oldClusters = clusters;
				findAllClusters();
				removeSubsetClusters();
				decideWhichClustersToAttemptDeduction();

				// For each cluster: find every combination of flags that satisfies all hints in the cluster
				for (const cluster of clusters) {

					// If this cluster is not marked for deduction, skip it
					if (!cluster.attemptDeduction) continue;

					const alwaysSafeCells = [...cluster.unknownCells];
					const alwaysFlaggedCells = [...cluster.unknownCells];

					// Try every legal combination of flags
					doHardDeductionHelper(cluster, alwaysSafeCells, alwaysFlaggedCells);

					// If a cell is always safe, open it; or always flagged, flag it
					alwaysSafeCells.forEach(c => {
						if (handleLeftClick(c.row, c.col)) madeChanges = true;
					});
					alwaysFlaggedCells.forEach(c => {
						if (handleRightClick(c.row, c.col)) madeChanges = true;
					});

					//if (madeChanges) return true;
				}
				
				// console.log('Always flagged: ' + horizonCoveredCells.filter(c => c.alwaysFlagged).map(c => '(' + c.row + ', ' + c.col + ')'));
				// console.log('Always safe: ' + horizonCoveredCells.filter(c => c.alwaysSafe).map(c => '(' + c.row + ', ' + c.col + ')'));

				return madeChanges;
			}

			let callCounter = 0;

			let depth = 1;
			// Recursively makes every valid flag guess for a cluster
			// Returns the minimum number of TOTAL flags when this cluster is completed
			// Returns -1 if the recursion was aborted for going too deep; ignore results if this happens
			function doHardDeductionHelper (cluster, alwaysSafeCells, alwaysFlaggedCells) {
				callCounter++;

				// Base case: if all hints satisfied and did not exceed flag limit: update "always flagged" and "always safe"
				if (cluster.hintCells.every(c => c.neighborFlags === c.neighborMines)) {

					// If used more flags than are available, this configuration fails
					if (boardData.numFlags > boardData.numMines) return;

					// Configuration succeeded; mark which cells are no longer always safe or always flagged

					// If cell is flagged, remove it from always safe
					alwaysSafeCells.filter(c => c.flagged).forEach(c => {
						alwaysSafeCells.splice(alwaysSafeCells.indexOf(c), 1);
					});

					// If cell is safe, remove it from always flagged
					alwaysFlaggedCells.filter(c => !c.flagged).forEach(c => {
						alwaysFlaggedCells.splice(alwaysFlaggedCells.indexOf(c), 1);
					});

					return;
				}

				// Base case: If any cell has too many flags, this configuration fails
				if (allCells.find(c => c.neighborFlags > c.neighborMines)) return;

				// General case: make guesses

				// Find a hint cell that is incomplete
				const cell = cluster.hintCells.find(c => c.neighborFlags < c.neighborMines);
				const flagsNeeded = cell.neighborMines - cell.neighborFlags;

				// Find every combination of flags to complete this hint
				const flagCombinations = getPowerSet(cell.neighbors.filter(n => !n.revealed && !n.flagged), flagsNeeded, flagsNeeded);

				// Try every flag combination
				for (const flagCombination of flagCombinations) {

					// Flag all suggested cells, to complete this hint
					for (const cellToFlag of flagCombination) handleRightClick(cellToFlag.row, cellToFlag.col);

					// Recurse to guess the next hint
					depth++;
					if (depth > workTimes.maxDepth) workTimes.maxDepth = depth;
					doHardDeductionHelper(cluster, alwaysSafeCells, alwaysFlaggedCells);
					depth--;

					// Unflag these cells
					for (const cellToFlag of flagCombination) handleRightClick(cellToFlag.row, cellToFlag.col);
				}

				return;
			}

			function getPowerSet(array, minSizeOfSubset = 0, maxSizeOfSubset = Infinity) {
				const subsets = [[]];
				
				for (const element of array) {
					const last = subsets.length - 1;
					for (let i = 0; i <= last; i++) {
						if (subsets[i] >= maxSizeOfSubset) continue;
						subsets.push([...subsets[i], element]);
					}
				}
				
				return subsets.filter(element => element.length >= minSizeOfSubset);
			}

			function removeSubsetClusters() {
				const newClustersArray = [];
				for (const a of clusters) {
					let aIsSubset = false;
					for (const b of clusters) {
						if (a !== b && a.hintCells.every(cell => b.hintCells.includes(cell))) {
							// a is a subset of b
							aIsSubset = true;
							break;
						}
					}
					if (!aIsSubset) newClustersArray.push(a);
				}
				clusters = newClustersArray;
			}


			// Each hint cell has its own cluster:
			// together with all hint cells that are connected to the main hint cell by a shared unknown cell
			// i.e. if you can go from: main hint cell -> unknown neighbor -> other hint cell, then other hint cell is in the cluster
			function findAllClusters () {
				clusters = [];

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

					const cluster = {
						// Remove duplicates
						hintCells: [...new Set(clusterHintCells)],
						unknownCells: [...new Set(clusterUnknownCells)],
						attemptDeduction: true
					};
					
					// If this number is ever inaccurate, then the cluster changed and we can work on it again
					cluster.numUnknownCellsWhenClusterWasCreated = cluster.unknownCells.length;

					clusters.push(cluster);
				}
			}

			function decideWhichClustersToAttemptDeduction () {
				for (cluster of clusters) {

					// Check if this cluster already existed the last time 
					const oldVersionOfCluster = oldClusters.find(oldCluster =>
						oldCluster.hintCells.length === cluster.hintCells.length
						&& oldCluster.hintCells.every(c => cluster.hintCells.includes(c))
					);

					// If this cluster is brand new, keep the new cluster
					if (!oldVersionOfCluster) continue;

					// If cluster has since been changed, keep the new cluster
					if (oldVersionOfCluster.numUnknownCellsWhenClusterWasCreated !== cluster.numUnknownCellsWhenClusterWasCreated) continue;

					// This cluster already exists and has not changed, do not try and deduce it
					cluster.attemptDeduction = false;
				}
			}

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

			function randomlyFillOrClearHint () {

				for (const hintCell of shuffleArray(hintCells)) {
					const choices = ['fill', 'clear'];
					
					// coveredFarCells: covered unflagged cells that are not neighboring hintCell
					const coveredFarCells = allCells.filter(c => !c.revealed && !c.flagged && !hintCell.neighbors.includes(c));

					// If there are not enough cells to move mines away to, cannot choose clear
					const availableCellsForMines = coveredFarCells.filter(c => !c.isMine);
					const minesToRemove = hintCell.neighbors.filter(n => !n.revealed && !n.flagged && n.isMine);
					if (availableCellsForMines.length < minesToRemove.length) {
						choices.splice(choices.indexOf('clear'), 1);
					}

					// If there are not enough mines to bring to this hint, cannot choose fill
					const availableMinesToBring = coveredFarCells.filter(c => c.isMine);
					const cellsToAddMine = hintCell.neighbors.filter(n => !n.revealed && !n.flagged && !n.isMine);
					if (availableMinesToBring.length < cellsToAddMine.length) {
						choices.splice(choices.indexOf('fill'), 1);
					}

					// If filling would create a boxed in group of unknown cells, cannot choose fill
					// Simulate fill
					

					// Make choice of which action
					let choice;

					// Both choices are available
					if (choices.length === 2) {
						// If there is only 1 hint to work with, prefer clear (to avoid being boxed in)
						if (hintCells.length === 1) choice = 'clear';
						// Don't pick the same choice N+1 times in a row
						else if (boardData.lastNPerturbanceChoices.every(s => s === 'fill')) choice = 'clear';
						else if (boardData.lastNPerturbanceChoices.every(s => s === 'clear')) choice = 'fill';
						// Pick randomly
						else choice = getRandomElement(choices);
					}
					// 1 choice available; pick it
					else if (choices.length === 1) {
						choice = choices[0];
					}
					// Cannot clear nor fill: try next horizon hint cell
					else {
						continue;
					}

					// Update history of choices
					boardData.lastNPerturbanceChoices.shift();
					boardData.lastNPerturbanceChoices.push(choice);

					// Surround hint with mines (only the covered cells)
					if (choice === 'fill') {
						let excessMines = 0;
						cellsToAddMine.forEach(n => {
							n.isMine = true;
							excessMines++;
						});

						// Remove excess mines
						shuffleArray(availableMinesToBring).slice(0, excessMines).forEach(c => {
							c.isMine = false;
						});
					}

					// Clear all mines around hint
					else if (choice === 'clear') {
						let mineDeficit = 0;
						minesToRemove.forEach(n => {
							n.isMine = false;
							mineDeficit++;
						});

						// Add mines to make up deficit
						shuffleArray(availableCellsForMines).slice(0, mineDeficit).forEach(c => {
							c.isMine = true;
						})
					}

					else {
						alert('An impossible choice was made');
					}

					updateMineInfo();
					return true;
				}

				// Failed to do an action
				return false;
			}

			/*
			TODO: 
			- for fill and clear, have a preference for choosing to move mines that are on the horizon
			- choose clear not just if it's the last hint, but if it's the last hint for that island of unknown cells
			- after mines are generated, rerun solver to see if it's actually deducible (why isn't it??)
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
