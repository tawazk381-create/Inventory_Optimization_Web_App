% File: app/octave/test_worker.m
% Simple test harness for worker_runner.m

disp('--- Running Optimization Worker Test ---');

% Build a temporary CSV file
tmpIn  = [tempname(), '.csv'];
tmpOut = [tempname(), '.json'];

fid = fopen(tmpIn, 'w');
fprintf(fid, 'id,avg_daily_demand,lead_time_days,unit_cost,safety_stock,order_cost\n');
fprintf(fid, '1,10,5,20,2,50\n');   % Item 1: avg 10/day, 5-day lead, cost 20, safety 2, order cost 50
fprintf(fid, '2,25,3,15,5,40\n');   % Item 2
fprintf(fid, '3,5,7,30,1,60\n');    % Item 3
fclose(fid);

% Call the worker
try
  worker_runner(tmpIn, tmpOut);
  disp(['Results written to ', tmpOut]);

  % Load and display the JSON output
  fid = fopen(tmpOut, 'r');
  jsonText = fread(fid, '*char')';
  fclose(fid);

  disp('--- JSON Results ---');
  disp(jsonText);

catch ME
  fprintf(2, 'Test failed: %s\n', ME.message);
end

% Cleanup files (optional, comment if you want to inspect them)
% delete(tmpIn);
% delete(tmpOut);
