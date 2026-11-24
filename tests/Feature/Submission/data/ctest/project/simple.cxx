#include <iostream>

int main(int ac, char* av[])
{
  (void)ac;
  (void)av;
  std::cout << "<DartMeasurement name=\"Answer\" type=\"numeric/double\">42</DartMeasurement>" << std::endl;
  return 0;
}

//uncovered function
int foo()
{
  int bar = 0;
  bar = 1;
  bar = 2;
  bar = 3;
  bar = 4;
  bar = 5;
  bar = 6;
  bar = 7;
  bar = 8;
  bar = 9;
  return bar;
}
