cmake_minimum_required (VERSION 2.6)
PROJECT(Simple2)
INCLUDE(CTest)

ADD_EXECUTABLE (simple simple.cxx)
ADD_TEST (simple simple)
