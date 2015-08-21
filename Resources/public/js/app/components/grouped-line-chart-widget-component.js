define(['d3', 'd3-tip', 'underscore'], function (d3, d3tip, _) {

  "use strict";

  var RATIO = 16 / 9,
      resizeGroupedLine = {},
      dateFormat = d3.time.format("%Y-%m-%d"),
      parseDate = dateFormat.parse,
      template = _.template(
          '<div class="tooltip-arrow"></div>' +
          '<div class="tooltip-inner">' +
            'Date: <span><%= date %></span>' +
            '<ul>' +
              '<% _.each(states, function(state){ %>' +
              '<li>' +
                  '<span class="color-label" style="background:<%= state.color %>"></span>' +
                  '<%= state.name %> tickets: <%= state.value %>' +
              '</li>' +
              '<% }) %>' +
            '</ul>' +
          '</div>'
      );

  window.addEventListener('resize', _.debounce(function(){
    for(var key in resizeGroupedLine) {
      if(resizeGroupedLine.hasOwnProperty(key)){
        resizeGroupedLine[key]();
      }
    }
  }, 100), false);

  return function (options) {

    var data = _.map(options.data, function(value, key){ value.date = parseDate(key); return value;}),
        parent = options.parent.el,
        elem = options._sourceElement.get(0),
        plot = d3.select(elem);

    var w = elem.clientWidth,
        h = w / RATIO,
        h2 = 100,
        margin = {top: 20, right: 40, bottom: 30, left: 40},
        width = w - margin.left - margin.right,
        height = h - margin.top - margin.bottom - (h2 + margin.top);

    var svg = plot.append("svg")
        .attr("width", w)
        .attr("height", h)
        .attr("viewBox", "0 0 " + w + " " + h);

    var focus = svg.append("g")
        .attr("class", "focus")
        .attr("transform", "translate(" + margin.left + "," + margin.top + ")");

    var context = svg.append("g")
        .attr("class", "context")
        .attr("transform", "translate(" + margin.left + "," + (margin.top * 2 + height) + ")");

    svg.append("defs").append("clipPath")
        .attr("id", "clip")
        .append("rect")
        .attr("width", width)
        .attr("height", height);

    var x = d3.time.scale().range([0, width]),
        x2 = d3.time.scale().range([0, width]),
        y = d3.scale.linear().range([height, 0]),
        y2 = d3.scale.linear().range([h2, 0]);

    var color = d3.scale.category10();

    color.domain(d3.keys(data[0]).filter(function(key) { return key !== "date"; }));

    var xAxis = d3.svg.axis().scale(x).orient("bottom"),
        xAxis2 = d3.svg.axis().scale(x2).orient("bottom"),
        yAxis = d3.svg.axis().scale(y).orient("left").ticks(1);

    var brushed = function() {
      x.domain(brush.empty() ? x2.domain() : brush.extent());
      focus.select(".x.axis").call(xAxis);
      focus.selectAll('.line')
          .attr("d", function(d) { return line(d.values); });
      focus.selectAll('.tooltip-holder')
          .attr("transform", function(d){ return "translate(" + (x(d.date) - 5) + ",0)"})
    };

    var brush = d3.svg.brush()
        .x(x2)
        .on("brush", brushed);

    var line = d3.svg.line()
        .interpolate("monotone")
        .x(function(d) { return x(d.date); })
        .y(function(d) { return y(d.state); });

    var line2 = d3.svg.line()
        .interpolate("monotone")
        .x(function(d) { return x2(d.date); })
        .y(function(d) { return y2(d.state); });

    var tickets = color.domain().map(function(name) {
      return {
        name: name,
        values: data.map(function(d) {
          return {date: d.date, state: +d[name]};
        })
      };
    });

    var tip = d3tip()
        .attr('class', 'diam-d3-tip tooltip top')
        .html(function(d) {
          var _data = {
            date : dateFormat(d.date),
            states : _.map(_.omit(d, 'date'), function(value, name){
                      return {
                        name : name,
                        value : value,
                        color: color(name)
                      }
                    })
          };
          return template(_data);
        });

    focus.call(tip);

    x.domain(d3.extent(data, function(d) { return d.date; }));

    y.domain([
      d3.min(tickets, function(c) { return d3.min(c.values, function(v) { return v.state; }); }),
      d3.max(tickets, function(c) { return d3.max(c.values, function(v) { return v.state + 1; }); })
    ]);

    x2.domain(x.domain());
    y2.domain(y.domain());

    focus.append("g")
        .attr("class", "x axis")
        .attr("transform", "translate(0," + height + ")")
        .call(xAxis);

    context.append("g")
        .attr("class", "x axis")
        .attr("transform", "translate(0," + h2 + ")")
        .call(xAxis2);

    focus.append("g")
        .attr("class", "y axis")
        .call(yAxis)
        .append("text")
        .attr("transform", "rotate(-90)")
        .attr("y", 6)
        .attr("dy", ".71em")
        .style("text-anchor", "end")
        .text("Tickets State");

    var ticket = focus.selectAll(".ticket")
        .data(tickets)
        .enter().append("g")
        .attr("class", "ticket")
        .style("clip-path", "url('#clip')");

    var ticket2 = context.selectAll(".ticket")
        .data(tickets)
        .enter().append("g")
        .attr("class", "ticket");

    ticket.append("path")
        .attr("class", "line")
        .attr("d", function(d) { return line(d.values); })
        .style("stroke", function(d) { return color(d.name); });

    ticket2.append("path")
        .attr("class", "line")
        .attr("d", function(d) { return line2(d.values); })
        .style("stroke", function(d) { return color(d.name); });


    focus.selectAll('.tooltip-holder')
        .data(data).enter()
        .append('g')
        .attr('class', 'tooltip-holder')
        .attr("transform", function(d){ return "translate(" + (x(d.date) - 10) + ",0)";})
        .append("rect")
        .attr('width', 20)
        .attr('height', height)
        .on('mouseover', tip.show)
        .on('mouseout', tip.hide);

    focus.selectAll('.tooltip-holder')
        .append('line')
        .attr('y2', height)
        .attr('x1', 10)
        .attr('x2', 10);

    context.append("g")
        .attr("class", "x brush")
        .call(brush)
        .selectAll("rect")
        .attr("y", -6)
        .attr("height", h2 + 7);

    //ticket.append("text")
    //    .datum(function(d) { return {name: d.name, value: d.values[d.values.length - 1]}; })
    //    .attr("transform", function(d) { return "translate(" + x(d.value.date) + "," + y(d.value.state) + ")"; })
    //    .attr("x", 3)
    //    .attr("dy", ".35em")
    //    .text(function(d) { return d.name; });

    resizeGroupedLine[parent.id] = function () {
      var w = elem.clientWidth,
          h = w / RATIO,
          width = w - margin.left - margin.right,
          height = h - margin.top - margin.bottom - (h2 + margin.top);

      x.range([0, width]);
      x2.range([0, width]);
      y.range([height, 0]);

      xAxis.scale(x);
      xAxis2.scale(x2);
      yAxis.scale(y);

      svg.attr("viewBox", "0 0 " + w + " " + h);

      svg.select("#clip").select("rect")
          .attr("width", width)
          .attr("height", height);

      context.attr("transform", "translate(" + margin.left + "," + (margin.top * 2 + height) + ")");

      focus.select('.x.axis')
          .attr("transform", "translate(0," + height + ")")
          .call(xAxis);

      focus.select('.y.axis')
          .call(yAxis);

      focus.selectAll('.tooltip-holder')
          .attr("transform", function(d){ return "translate(" + (x(d.date) - 5) + ",0)"})
          .select('rect')
          .attr('height', height);

      context.select('.x.axis')
          .call(xAxis2);

      focus.selectAll('.line').attr("d", function(d) { return line(d.values); });
      context.selectAll('.line').attr("d", function(d) { return line2(d.values); });
    };

  };

});