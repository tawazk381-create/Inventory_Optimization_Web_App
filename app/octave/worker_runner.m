% File: app/octave/worker_runner.m
% Reads CSV input, computes EOQ & reorder points, writes JSON results to outfile.

function worker_runner(infile, outfile)
  if nargin < 2
    error('Usage: worker_runner(infile, outfile)');
  end
  if ~exist(infile, 'file')
    error('Input file not found: %s', infile);
  end

  try
    % -------------------------
    % Read CSV (skip header row)
    % -------------------------
    fid = fopen(infile, 'r');
    if fid == -1
      error('Cannot open input file %s', infile);
    end
    headerLine = fgetl(fid); %#ok<NASGU> % discard header
    data = textscan(fid, '%f%f%f%f%f%f', 'Delimiter', ',', 'CollectOutput', true);
    fclose(fid);

    if isempty(data) || isempty(data{1})
      error('No numeric data found in %s', infile);
    end
    data = data{1};
    n = size(data, 1);

    % -------------------------
    % Compute EOQ & Reorder Point
    % -------------------------
    results = struct('item_id', {}, 'eoq', {}, 'reorder_point', {}, 'safety_stock', {});
    for i = 1:n
      id          = data(i,1);
      avg_daily   = data(i,2);
      lead_days   = data(i,3);
      unit_cost   = data(i,4);
      safety      = data(i,5);
      order_cost  = data(i,6);

      % Defensive defaults
      if isempty(avg_daily) || ~isfinite(avg_daily) || avg_daily <= 0
        avg_daily = 1;
      end
      if isempty(lead_days) || ~isfinite(lead_days) || lead_days <= 0
        lead_days = 1;
      end
      if isempty(unit_cost) || ~isfinite(unit_cost) || unit_cost <= 0
        unit_cost = 1.0;
      end
      if isempty(order_cost) || ~isfinite(order_cost) || order_cost <= 0
        order_cost = 50;
      end
      if isempty(safety) || ~isfinite(safety) || safety < 0
        safety = 0;
      end

      % Annual demand
      D = max(1, avg_daily * 365);
      holding_rate = 0.25;   % 25% annual holding cost rate
      H = max(1e-9, unit_cost * holding_rate);

      % Economic Order Quantity (EOQ)
      Q = sqrt((2 * D * order_cost) / H);
      eoq = round(Q);

      % Reorder Point (demand during lead time + safety stock)
      reorder_point = round(lead_days * avg_daily + safety);

      % Append to results
      results(i).item_id       = id;
      results(i).eoq           = eoq;
      results(i).reorder_point = reorder_point;
      results(i).safety_stock  = round(safety);
    end

    % -------------------------
    % Convert results to JSON
    % -------------------------
    try
      if exist('jsonencode', 'file') == 2 || exist('jsonencode', 'builtin') == 5
        json_text = jsonencode(results);
      else
        % Fallback manual JSON builder
        parts = cell(1,n);
        for i=1:n
          r = results(i);
          parts{i} = sprintf('{"item_id":%d,"eoq":%d,"reorder_point":%d,"safety_stock":%d}', ...
                             r.item_id, r.eoq, r.reorder_point, r.safety_stock);
        end
        json_text = ['[', strjoin(parts, ','), ']'];
      end
    catch ME
      error('Failed to encode JSON: %s', ME.message);
    end

    % -------------------------
    % Write JSON output atomically
    % -------------------------
    tmpOut = [outfile, '.tmp'];
    fo = fopen(tmpOut, 'w');
    if fo == -1
      error('Failed to open output file %s for writing', tmpOut);
    end
    fprintf(fo, '%s', json_text);
    fflush(fo);
    fclose(fo);

    % Move tmp file into place
    movefile(tmpOut, outfile);

  catch ME
    % Print error so PHP sees it
    fprintf(2, 'worker_runner error: %s\n', ME.message);
    exit(1);
  end
end
